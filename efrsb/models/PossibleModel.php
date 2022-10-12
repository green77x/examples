<?php

namespace app\efrsb\models;

use app\models\AutoModel;
use Yii;

/**
 * This is the model class for table "efrsb_possible_model".
 *
 * @property int $id
 * @property int $possibleBrandId
 * @property int $modelId
 *
 * @property AutoModel $model
 * @property EfrsbPossibleBrand $possibleBrand
 */
class PossibleModel extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efrsb_possible_model';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['possibleBrandId', 'modelId'], 'integer'],
            [['modelId'], 'exist', 'skipOnError' => true, 'targetClass' => AutoModel::className(), 'targetAttribute' => ['modelId' => 'id']],
            [['possibleBrandId'], 'exist', 'skipOnError' => true, 'targetClass' => PossibleBrand::className(), 'targetAttribute' => ['possibleBrandId' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'possibleBrandId' => 'Possible Brand ID',
            'modelId' => 'Model ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getModel()
    {
        return $this->hasOne(AutoModel::className(), ['id' => 'modelId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPossibleBrand()
    {
        return $this->hasOne(PossibleBrand::className(), ['id' => 'possibleBrandId']);
    }
}
