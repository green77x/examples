<?php

namespace app\telegram\models;

use Yii;

/**
 * This is the model class for table "telegram_subscribe_request".
 *
 * @property int $chatId
 * @property string $username
 * @property string $firstName
 * @property string $lastName
 * @property string $phone
 * @property string $workingHoursStart
 * @property string $workingHoursEnd
 * @property string $minStartPrice
 * @property string $maxStartPrice
 * @property string $regions
 * @property string $categories
 * @property string $dateCreate
 * @property string $dateUpdate
 */
class SubscribeRequest extends \app\models\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'telegram_subscribe_request';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['workingHoursStart', 'workingHoursEnd', 'dateCreate', 'dateUpdate'], 'safe'],
			[['minStartPrice', 'maxStartPrice'], 'string'],
			[['regions', 'categories'], 'string'],
			[['username', 'firstName', 'lastName'], 'string', 'max' => 255],
			[['phone'], 'string', 'max' => 15],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'chatId' => 'Chat ID',
			'username' => 'Username',
			'firstName' => 'First Name',
			'lastName' => 'Last Name',
			'phone' => 'Phone',
			'workingHoursStart' => 'Working Hours Start',
			'workingHoursEnd' => 'Working Hours End',
			'minStartPrice' => 'Start Price Min',
			'maxStartPrice' => 'Start Price Max',
			'regions' => 'Regions',
			'categories' => 'Categories',
			'dateCreate' => 'Date Create',
			'dateUpdate' => 'Date Update',
		];
	}


	public function addCategory(string $categoryCode)
	{
		$categoriesArray = $this->categoriesArray;
		if (!in_array($categoryCode, $categoriesArray)) {
			$categoriesArray[] = $categoryCode;
		}

		$this->categories = implode(';', $categoriesArray);
	}


	public function removeCategory(string $categoryCode)
	{
		$categoriesArray = $this->categoriesArray;

		if (($key = array_search($categoryCode, $categoriesArray)) !== false) {
			unset($categoriesArray[$key]);
		}

		$this->categories = implode(';', $categoriesArray);
	}


	public function addRegion(string $regionCode)
	{
		$regionsArray = $this->regionsArray;
		if (!in_array($regionCode, $regionsArray)) {
			$regionsArray[] = $regionCode;
		}

		$this->regions = implode(';', $regionsArray);
	}


	public function removeRegion(string $regionCode)
	{
		$regionsArray = $this->regionsArray;

		if (($key = array_search($regionCode, $regionsArray)) !== false) {
			unset($regionsArray[$key]);
		}

		$this->regions = implode(';', $regionsArray);
	}



	public function getCategoriesArray()
	{
		# если строка пустая, нужно в явном виде вернуть пустой массив,
		# потому что explode(';', '') вернет не пустой массив, а массив с одним элементом - пустой строкой.
		# @see http://php.net/manual/ru/function.explode.php комментарии
		if (empty($this->categories)) {
			return [];
		}

		return explode(';', $this->categories);
	}


	public function getRegionsArray()
	{
		# если строка пустая, нужно в явном виде вернуть пустой массив,
		# потому что explode(';', '') вернет не пустой массив, а массив с одним элементом - пустой строкой.
		# @see http://php.net/manual/ru/function.explode.php комментарии
		if (empty($this->regions)) {
			return [];
		}

		return explode(';', $this->regions);
	}
}
