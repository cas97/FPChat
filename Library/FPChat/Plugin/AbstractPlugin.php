<?php

namespace FPChat\Plugin;

abstract class AbstractPlugin
{
	/**
	 * Triggered on a chat line
	 *
	 * @param \FPChat\Bot $bot
	 * @param \FPChat\Line $line
	 */
	public function onLine($bot, $line) {}

	/**
	 * Triggered on a mention
	 *
	 * @param \FPChat\Bot $bot
	 * @param \FPChat\Line $line
	 */
	public function onMention($bot, $line) {}

	/**
	 * Triggered on quit
	 *
	 * @param \FPChat\Bot $bot
	 * @param \FPChat\User $user
	 */
	public function onQuit($bot, $user) {}

	/**
	 * Triggered on join
	 *
	 * @param \FPChat\Bot $bot
	 * @param \FPChat\User $user
	 */
	public function onJoin($bot, $user) {}
}
