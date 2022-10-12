<?php

namespace app\telegram\models;

use Yii;

/**
 * This is the model class for table "telegram_subscriber_region_xref".
 *
 * @property int $chatId
 * @property int $regionCode
 * @property string $dateCreate
 * @property string $dateUpdate
 *
 * @property Subscriber $chat
 */
class SubscriberRegionXref extends \app\models\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'telegram_subscriber_region_xref';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['chatId', 'regionCode'], 'required'],
            [['chatId', 'regionCode'], 'integer'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            [['chatId', 'regionCode'], 'unique', 'targetAttribute' => ['chatId', 'regionCode']],
            [['chatId'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::className(), 'targetAttribute' => ['chatId' => 'chatId']],
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
            'dateCreate' => 'Date Create',
            'dateUpdate' => 'Date Update',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChat()
    {
        return $this->hasOne(Subscriber::className(), ['chatId' => 'chatId']);
    }
}
