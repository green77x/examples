<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "sync_error_log".
 *
 * @property int $id
 * @property string $tradeEtpId
 * @property string $tradeINN
 * @property string $error
 * @property string $dateCreate
 * @property string $dateUpdate
 */
class SyncErrorLog extends \app\models\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sync_error_log';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tradeEtpId', 'tradeINN', 'error'], 'required'],
            [['error'], 'string'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            [['tradeEtpId'], 'string', 'max' => 255],
            [['tradeINN'], 'string', 'max' => 12],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tradeEtpId' => 'Trade Etp ID',
            'tradeINN' => 'Trade Inn',
            'error' => 'Error',
            'dateCreate' => 'Date Create',
            'dateUpdate' => 'Date Update',
        ];
    }
}
