<?php

namespace app\telegram\models;

use app\telegram\services\{CategoryService, RegionService, PriceService};
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "telegram_subscriber".
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
 * @property string $dateCreate
 * @property string $dateUpdate
 *
 * @property SubscriberCategoryXref[] $telegramSubscriberCategoryXrefs
 * @property SubscriberRegionXref[] $telegramSubscriberRegionXrefs
 */
class Subscriber extends \app\models\ActiveRecord implements \app\telegram\menus\SettingsInterface
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'telegram_subscriber';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['workingHoursStart', 'workingHoursEnd', 'dateCreate', 'dateUpdate'], 'safe'],
			[['minStartPrice', 'maxStartPrice'], 'number'],
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
			'dateCreate' => 'Date Create',
			'dateUpdate' => 'Date Update',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getSubscriberCategoryXrefs()
	{
		return $this->hasMany(SubscriberCategoryXref::className(), ['chatId' => 'chatId']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getSubscriberRegionXrefs()
	{
		return $this->hasMany(SubscriberRegionXref::className(), ['chatId' => 'chatId']);
	}


	/**
	 * возвращает массив категорий по данной подписке
	 * @return type
	 */
	public function getTgCategoriesArray()
	{
		$rows = (new Query)
			->select('categoryCode')
			->from(SubscriberCategoryXref::tableName())
			->andWhere(['chatId' => $this->chatId])
			->all();

		return ArrayHelper::map($rows, 'categoryCode', 'categoryCode');
	}


	public function getEfrsbCategoriesArray()
	{
		return CategoryService::getEfrsbCategories($this->tgCategoriesArray);
	}


	public function getTgCategoryTitles()
	{
		return CategoryService::getTgCategoryTitles($this->tgCategoriesArray);
	}


	/**
	 * возвращает массив регионов по данной подписке
	 * @return type
	 */
	public function getRegionsArray()
	{
		$rows = (new Query)
			->select('regionCode')
			->from(SubscriberRegionXref::tableName())
			->andWhere(['chatId' => $this->chatId])
			->all();

		return ArrayHelper::map($rows, 'regionCode', 'regionCode');
	}


	public function getRegionTitles()
	{
		return RegionService::getRegionTitles($this->regionsArray);
	}


	public function getPriceText()
	{
		return PriceService::getPriceText($this->minStartPrice, $this->maxStartPrice);
	}


	public function addTgCategory(string $tgCategory)
	{
		# проверяем, есть ли уже такая категория
		$options = ['chatId' => $this->chatId, 'categoryCode' => $tgCategory];
		if ($xref = SubscriberCategoryXref::findOne($options)) {
			return;
		}

		$xref = new SubscriberCategoryXref($options);
		$xref->saveEx();
	}


	public function removeTgCategory(string $tgCategory)
	{
		# проверяем, есть ли уже такая категория
		$options = ['chatId' => $this->chatId, 'categoryCode' => $tgCategory];
		if ($xref = SubscriberCategoryXref::findOne($options)) {
			$xref->delete();
		}
	}


	public function addRegion(string $region)
	{
		# проверяем, есть ли уже такой регион
		$options = ['chatId' => $this->chatId, 'regionCode' => $region];
		if ($xref = SubscriberRegionXref::findOne($options)) {
			return;
		}

		$xref = new SubscriberRegionXref($options);
		$xref->saveEx();
	}


	public function removeRegion(string $region)
	{
		# проверяем, есть ли уже такой регион
		$options = ['chatId' => $this->chatId, 'regionCode' => $region];
		if ($xref = SubscriberRegionXref::findOne($options)) {
			$xref->delete();
		}
	}

}
