<?php

namespace FPChat;
use FPChat\Exception;

class Bot
{
	const REGEX_SECURITYTOKEN = '#var SECURITYTOKEN = \'(?<token>\d+-[a-z0-9]+)\';#';
	const REGEX_LASTLINE = '#var LastLine = (?<lastline>\d+);#';

	/**
	 * @var Zend\Http\Client
	 */
	private $_httpClient;

	/**
	 * @var Zend\Uri\Url
	 */
	private $_url;

	/**
	 * Indicates whether or not the user has logged in yet
	 *
	 * @var bool
	 */
	private $_loggedIn = false;

	private $_securityToken = '';

	private $_lastLine = 0;

	public function __construct()
	{
		$this->_url = new \Zend\Uri\Url('http://www.facepunch.com/');
		$this->_httpClient = new \Zend\Http\Client($this->_url, array('adapter' => 'Zend\\Http\\Client\\Adapter\\Curl'));
		$this->_httpClient->setCookieJar(true);

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
			$nextTick = time() + 10;

			// Make an AJAX chat call
			$response = $this->_chatRequest();

			// Check to see if there's lines
			if (count($response->lines))
			{
				$lines = array_reverse($response->lines);

				foreach ($lines AS $line)
				{
					$line = (array) $line;
					$this->_lastLine = max($this->_lastLine, $line['id']);

					echo "({$line['id']})> {$line['html']}\n";
				}
			}

			sleep($nextTick - time());
		} while(true);
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
