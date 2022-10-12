<?php

namespace app\efrsb\models;

use app\models\User;
use Yii;

/**
 * This is the model class for table "favorite_user_lot_xref".
 *
 * @property int $userId
 * @property int $lotId
 * @property string $dateCreate
 * @property string $dateUpdate
 *
 * @property EfrsbLot $lot
 * @property User $user
 */
class FavoriteUserLotXref extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'favorite_user_lot_xref';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
			[['userId', 'lotId', 'tradeEtpId', 'lotNumber', 'tradePlaceInn'], 'required'],
			[['userId', 'lotId', 'lotNumber'], 'integer'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            ['tradeEtpId', 'string', 'max' => 255],
            ['tradePlaceInn', 'string', 'max' => 12],
            [['userId', 'lotId'], 'unique', 'targetAttribute' => ['userId', 'lotId']],
            [['lotId'], 'exist', 'skipOnError' => true, 'targetClass' => Lot::className(), 'targetAttribute' => ['lotId' => 'id']],
            [['userId'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['userId' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'userId' => 'User ID',
            'lotId' => 'Lot ID',
            'dateCreate' => 'Date Create',
            'dateUpdate' => 'Date Update',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLot()
    {
        return $this->hasOne(Lot::className(), ['id' => 'lotId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'userId']);
    }
}
