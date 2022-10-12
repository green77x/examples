<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "sync_log".
 *
 * @property int $id
 * @property int $status
 * @property string $intervalBeginning
 * @property string $intervalEnd
 * @property string $startTime
 * @property string $endTime
 * @property int $pid
 * @property string $dateCreate
 * @property string $dateUpdate
 */
class SyncLog extends \app\models\ActiveRecord
{

	const STATUS_RUNNING = 0;
	const STATUS_FINISHED = 1;
	const STATUS_FAILED = 2;


	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'sync_log';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['status'], 'required'],
			[['status', 'pid'], 'integer'],
			[['intervalBeginning', 'intervalEnd', 'startTime', 'endTime', 'dateCreate', 'dateUpdate'], 'safe'],
			['error', 'string'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => 'ID',
			'status' => 'Status',
			'intervalBeginning' => 'Interval Beginning',
			'intervalEnd' => 'Interval End',
			'startTime' => 'Start Time',
			'endTime' => 'End Time',
			'pid' => 'Pid',
			'dateCreate' => 'Date Create',
			'dateUpdate' => 'Date Update',
		];
	}


	public function getIsFinished(): bool
	{
		return ($this->status == self::STATUS_FINISHED);
	}


	public function getIsRunning(): bool
	{
		return ($this->status == self::STATUS_RUNNING);
	}


	public function getIsFailed(): bool
	{
		return ($this->status == self::STATUS_FAILED);
	}


	public static function findLast()
	{
		$last = self::find()
			->orderBy(['dateCreate' => SORT_DESC])
			->one();

		return $last;
	}
}
