<?php

namespace app\telegram\models;

use Yii;

/**
 * This is the model class for table "telegram_subscriber_category_xref".
 *
 * @property int $chatId
 * @property int $categoryCode
 * @property string $dateCreate
 * @property string $dateUpdate
 *
 * @property Subscriber $chat
 */
class SubscriberCategoryXref extends \app\models\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'telegram_subscriber_category_xref';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['chatId', 'categoryCode'], 'required'],
            [['chatId'], 'integer'],
            ['categoryCode', 'string'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            [['chatId', 'categoryCode'], 'unique', 'targetAttribute' => ['chatId', 'categoryCode']],
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
            'categoryCode' => 'Category Code',
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
