<?php

namespace app\telegram\interactions;

use app\services\Log;
use app\telegram\models\SubscribeRequest;
use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


final class NewUserInteraction extends BaseInteraction implements InteractionInterface
{

	const NU_1_YES = 'nu_1_yes';
	const NU_1_NO = 'nu_1_no';


	protected function processMessage(Message $message)
	{
		$text = $message->text;
		Log::logTelegramGotMessage($message);

		if ($text == self::START) {
			$this->processStartMessage($message);
		} elseif ($text == self::HELP) {
			$this->processHelpMessage($message);
		} else {
			$this->processUnknownMessage($message);
		}
	}


	/**
	 * Обрабатывает сообщение /start
	 *
	 * Отправляет сообщение с описанием своего функционала и предлагает оформить подписку.
	 * @param Message $message
	 * @return type
	 */
	protected function processStartMessage(Message $message)
	{
		$text = $this->getBotShortDescription() . PHP_EOL .
			'Предлагаю вам подписаться на получение лотов. Вы в любой момент сможете отписаться, если что-то не понравится.' .
				PHP_EOL . 'Что скажете?';

		$keyboard = $this->generateStartKeyboard();
		$this->telega->sendMessage($message->chat->id, $text, $keyboard);
	}


	/**
	 * Обрабатывает сообщение /help.
	 *
	 * Пока что делает то же самое, что и обработка стартового сообщения. В будущем можно как-нибудь покрасивее
	 * @param Message $message
	 * @return type
	 */
	protected function processHelpMessage(Message $message)
	{
		$this->processStartMessage($message);
	}


	protected function processUnknownMessage(Message $message)
	{
		$text = 'Извините, ваша команда нераспознана.' . PHP_EOL . PHP_EOL .
			'Если хотите узнать обо мне или оформить подписку на получение новых лотов, напишите /start';
		$this->telega->sendMessage($message->chat->id, $text);
	}


	/**
	 * Создает клавиатуру с ответами на стартовое предложение оформить подписку.
	 * @return type
	 */
	private function generateStartKeyboard()
	{
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text'		  => 'Да, хочу подписаться!',
					'callback_data' => self::NU_1_YES,
				]),
				Keyboard::inlineButton([
					'text'		  => 'Нет, попозже',
					'callback_data' => self::NU_1_NO,
				])
			)
		;

		return $keyboard;
	}


	protected function processCallbackQuery(CallbackQuery $query)
	{
		$data = $query->data;

		# в любом случае отправляем ответ на callbackQuery - так надо по документации
		# @see https://core.telegram.org/bots/api#answercallbackquery
		$this->telega->answerCallbackQuery($query);

		if ($data == self::NU_1_YES) {
			$this->processNewRequestYes($query);
		} elseif ($data == self::NU_1_NO) {
			$this->processNewRequestNo($query);
		} else {
			$this->processUnknownQuery($query);
		}
	}


	/**
	 * Обрабатывает нажатие на кнопку "Да" на предложение оформить подписку
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processNewRequestYes(CallbackQuery $query)
	{
		$user = $query->from;

		# создаем заявку на подписку
		$request = new SubscribeRequest([
			'chatId' => $user->id,
			'username' => $user->username,
			'firstName' => $user->first_name,
			'lastName' => $user->last_name,
		]);
		$request->saveEx();


		# отправляем сообщение пользователю
		$text = 'Отлично! Давайте выберем категории лотов, по которым вы хотите получать уведомления.';
		$keyboard = $this->generateCategoryKeyboard();
		$this->telega->sendMessage($request->chatId, $text, $keyboard);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
	}


	/**
	 * Обрабатывает нажатие на кнопку "Нет" на предложение оформить подписку
	 * @return type
	 */
	private function processNewRequestNo(CallbackQuery $query)
	{
		$text = 'Если вдруг передумаете - напишите /start, мы продолжим :)';
		$this->telega->sendMessage($query->from->id, $text);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
	}
}
