<?php

namespace app\efrsb\models;


use app\models\AutoBrand;
use Yii;

/**
 * This is the model class for table "efrsb_possible_brand".
 *
 * @property int $id
 * @property int $brandId
 * @property int $lotId
 *
 * @property AutoBrand $brand
 * @property EfrsbLot $lot
 * @property EfrsbPossibleModel[] $efrsbPossibleModels
 */
class PossibleBrand extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efrsb_possible_brand';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['brandId', 'lotId'], 'integer'],
            [['brandId'], 'exist', 'skipOnError' => true, 'targetClass' => AutoBrand::className(), 'targetAttribute' => ['brandId' => 'id']],
            [['lotId'], 'exist', 'skipOnError' => true, 'targetClass' => Lot::className(), 'targetAttribute' => ['lotId' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'brandId' => 'Brand ID',
            'lotId' => 'Lot ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBrand()
    {
        return $this->hasOne(AutoBrand::className(), ['id' => 'brandId']);
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
    public function getPossibleModels()
    {
        return $this->hasMany(PossibleModel::className(), ['possibleBrandId' => 'id']);
    }
}
