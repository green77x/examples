<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "telegram_sub".
 *
 * @property int $chatId
 * @property int $regionCode
 * @property string $classificationId
 * @property string $dateCreate
 * @property string $dateUpdate
 */
class TelegramSub extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'telegram_sub';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // [['regionCode', 'classificationId']],
            [['regionCode'], 'integer'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            [['classificationId'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'chatId' => 'Chat ID',
            'regionCode' => 'Region Code',
            'classificationId' => 'Classification ID',
            'dateCreate' => 'Date Create',
            'dateUpdate' => 'Date Update',
        ];
    }
}
