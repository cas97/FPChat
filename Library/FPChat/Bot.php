<?php

namespace FPChat;
use FPChat\Exception;

class Bot
{
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
		$this->_httpClient->setUri($this->_url);

		echo "Requesting...";
		$response = $this->_req('POST', true); echo "Done\nParsing...";
		$dom = new \Zend\Dom\Query($response->getBody()); echo "Done\n";

		// Check to see if we get the redirection
		$results = $dom->execute('.standard_error.redirect_page');

		if (!count($results))
		{
			throw new Exception\Exception('Login returned an error');
		}

		echo "Logged in\n";

		// Assume at this point the login worked
		$this->_loggedIn = true;
	}

	private function _req($method = 'GET', $raw = false)
	{
		$response = $this->_httpClient->request($method);

		if ($response->isError())
		{
			throw new Exception\HttpError('Facepunch returned an HTTP error');
		}

		if ($raw)
		{
			return $response;
		}
	}
}
