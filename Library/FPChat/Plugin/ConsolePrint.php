<?php

namespace FPChat\Plugin;

class ConsolePrint extends AbstractPlugin
{
	public function onLine($bot, $line)
	{
		echo '<' . $line->username . '> ' . $line->message . "\n";
	}
}
