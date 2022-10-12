<?php

namespace app\telegram\menus;


/**
 * Этот интерфейс должны реализовывать классы, которые поддерживают настройку категорий, регионов и цены лотов -
 * Subscriber и SubscribeRequest
 */
interface SettingsInterface
{

	/**
	 * Вовзращает массив телеграм-категорий данной сущности
	 * @return type
	 */
	public function getTgCategoriesArray();


	/**
	 * Добавляет телеграм-категорию к данной сущности
	 * @param string $tgCategory
	 * @return type
	 */
	public function addTgCategory(string $tgCategory);


	/**
	 * Убирает телеграм-категорию у данной сущности
	 * @param string $tgCategory
	 * @return type
	 */
	public function removeTgCategory(string $tgCategory);


	/**
	 * Возвращает массив регионов данной сущности
	 * @return type
	 */
	public function getRegionsArray();

	/**
	 * Добавляет регион к данной сущности
	 * @param string $region
	 * @return type
	 */
	public function addRegion(string $region);


	/**
	 * Убирает регион у данной сущности
	 * @param string $region
	 * @return type
	 */
	public function removeRegion(string $region);
}
