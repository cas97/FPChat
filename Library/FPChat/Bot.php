<?php

namespace FPChat;
use FPChat\Exception;

class Bot
{
	const REGEX_SECURITYTOKEN = '#var SECURITYTOKEN = \'(?<token>\d+-[a-z0-9]+)\';#';
	const REGEX_LASTLINE = '#var LastLine = (?<lastline>\d+);#';
	const REGEX_TIMESTMAP = '#<b>\[(?<time>\d+:\d+:\d+)\]</b>#';

	/**
	 * @var Zend\Http\Client
	 */
	private $_httpClient;

	/**
	 * @var Zend\Uri\Url
	 */
	private $_url;

	/**
	 * @var PluginBroker
	 */
	private $_plugin;

	/**
	 * Indicates whether or not the user has logged in yet
	 *
	 * @var bool
	 */
	private $_loggedIn = false;

	/**
	 * Security token needed for AJAX calls
	 *
	 * @var string
	 */
	private $_securityToken = '';

	/**
	 * Last line ID
	 *
	 * @var int
	 */
	private $_lastLine = 0;

	/**
	 * Names of users in chat by ID
	 *
	 * @var array
	 */
	private $_names = array();

	public function __construct()
	{
		$this->_url = new \Zend\Uri\Url('http://www.facepunch.com/');
		$this->_httpClient = new \Zend\Http\Client($this->_url, array('adapter' => 'Zend\\Http\\Client\\Adapter\\Curl'));
		$this->_httpClient->setCookieJar(true);
		$this->_plugin = new PluginBroker();

		echo "Inited\n";
	}

	public function login($username, $password)
	{
		$this->_url->setPath('/login.php'); //print_r($this->_url); die;
		$this->_httpClient->setParameterPost(array(
			'vb_login_username' => $username,
			'vb_login_password' => $password,
			'cookieuser' => '1',
			'securitytoken' => 'guest',
			'do' => 'login'
		));

		echo "Requesting...";
		$response = $this->_req('POST', true); echo "Done\nParsing...";
		$dom = new \Zend\Dom\Query($response->getBody()); echo "Done\n";

		// Check to see if we get the redirection
		$results = $dom->execute('.standard_error.redirect_page');

		if (!count($results))
		{
			throw new \Exception('Login returned an error');
		}

		echo "Logged in\n";

		// Assume at this point the login worked
		$this->_loggedIn = true;
	}

	public  function run()
	{
		if (!$this->_loggedIn)
		{
			throw new \Exception('Please log in before running bot');
		}

		$this->_setup();

		do {
			$nextTick = time() + 2;

			// Make an AJAX chat call
			try {
				$response = $this->_chatRequest();
			} catch (\Exception $e) {
				// A network error occurred, sleep and try again
				$this->_sleep($nextTick + 5);
				continue;
			}

			// Check to see if there's lines
			if (count($response->lines))
			{
				$lines = array_reverse($response->lines);

				foreach ($lines AS $line)
				{
					$line = (array) $line;
					$this->_lastLine = max($this->_lastLine, $line['id']);
					$lineBit = $this->_parseLine($line['id'], $line['html']);

					$this->_plugin->onLine($this, $lineBit);

					if ($lineBit->mentioned)
					{
						$this->_plugin->onMention($this, $lineBit);
					}
				}
			}

			// Process user join/quits
			if (count($response->names))
			{
				$newNames = $this->_parseNames($response->names);
				$currentNames = $this->_names;
				$quitNames = array_diff_key($currentNames, $newNames);
				$joinNames = array_diff_key($newNames, $currentNames);

				foreach ($quitNames AS $id => $name)
				{
					$this->_plugin->onQuit($this, $name);
				}

				foreach ($joinNames AS $id => $name)
				{
					$this->_plugin->onJoin($this, $name);
				}

				$this->_names = $newNames;
			}

			$this->_sleep($nextTick + 5);
		} while(true);
	}

	public function registerPlugin($code, Plugin\AbstractPlugin $plugin)
	{
		$this->_plugin->addPlugin($code, $plugin);
	}

	private function _sleep($nextTick)
	{
		sleep(max(($nextTick - time()), 1));
	}

	private function _parseNames($names)
	{
		$users = array();

		foreach ($names AS $name)
		{
			$user = new User;
			$user->id = $name->id;

			preg_match('#class=\'group(?<groupid>\d+)\'#', $name->name, $groupMatch);
			$user->groupId = $groupMatch['groupid'];

			$user->username = strip_tags($name->name);

			$users[$user->id] = $user;
		}

		return $users;
	}

	/**
	 * Parses messages
	 *
	 * @param  $id
	 * @param  $html
	 * @return \FPChat\Line
	 */
	private function _parseLine($id, $html)
	{
		$dom = new \Zend\Dom\Query($html);
		$line = new Line;
		$line->id = $id;
		/** @var $lineElement \DOMElement */
		$lineElement = $dom->execute('.line')->current();

		// Work out if we were mentioned
		$classes = explode(' ', $lineElement->getAttribute('class'));
		$line->mentioned = in_array('mentioned', $classes);

		// Get the timestamp
		preg_match(self::REGEX_TIMESTMAP, $html, $timestampMatch);
		$date = \DateTime::createFromFormat('H:i:s', $timestampMatch['time'], new \DateTimeZone('UTC'));
		$line->timestamp = $date->getTimestamp();

		// Get user information
		/** @var $userA \DOMElement */
		$userA = $dom->execute('.line span.username a')->current();
		$line->userId = (int) substr($userA->getAttribute('href'), 9);
		$line->username = $userA->nodeValue;

		// Get the message
		$userBit = $lineElement->getElementsByTagName('span')->item(0);
		$lineElement->removeChild($userBit);
		$line->message = $lineElement->nodeValue;

		return $line;
	}

	private function _chatRequest()
	{
		$this->_url->setPath('/chat/');
		$this->_httpClient->setParameterGet(array('aj' => 1, 'lastget' => $this->_lastLine));
		$response = $this->_req();
		//print_r($response);

		// NICE SPELLING GARRY
		if ($response->reponse != 'OK')
		{
			throw new \Exception('The response was not okay!!!');
		}

		$this->_securityToken = $response->token;

		return $response;
	}

    private function _setup()
    {
        // Get the needed stuff off the chat page
		$this->_url->setPath('/chat/');
		$response = $this->_req('GET', true);
		$body = $response->getBody();

		if (!preg_match(self::REGEX_SECURITYTOKEN, $body, $tokenMatches) || !preg_match(self::REGEX_LASTLINE, $body, $lineMatches))
		{
			throw new \Exception('Not able to extract the security token and/or last line from the page');
		}

		$this->_securityToken = $tokenMatches['token'];
		$this->_lastLine = $lineMatches['lastline'];

		// Populate the names list
		$response = $this->_chatRequest();
		$this->_names = $this->_parseNames($response->names);
	}

	private function _req($method = 'GET', $raw = false)
	{
		$this->_httpClient->setUri($this->_url);
		$response = $this->_httpClient->request($method);
		$this->_httpClient->resetParameters();

		if ($response->isError())
		{
			throw new Exception\HttpError('Facepunch returned an HTTP error');
		}

		if ($raw)
		{
			return $response;
		}

		return json_decode($response->getBody());
	}
}
