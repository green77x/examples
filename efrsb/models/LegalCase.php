<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "efrsb_legal_case".
 *
 * @property int $id
 * @property string $caseNumber
 * @property string $courtName
 * @property string $base
 *
 * @property EfrsbTrade[] $efrsbTrades
 */
class LegalCase extends \app\models\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efrsb_legal_case';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['caseNumber', 'courtName'], 'required'],
            [['base'], 'string'],
            [['caseNumber'], 'string', 'max' => 60],
            [['courtName'], 'string', 'max' => 300],
            [['caseNumber'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'caseNumber' => 'Номер дела',
            'courtName' => 'Наименование суда',
            'base' => 'Основание для проведения торгов',
            'tradeCount' => 'Количество торгов',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTrades()
    {
        return $this->hasMany(Trade::className(), ['legalCaseId' => 'id']);
    }


    public function getTradeCount()
    {
        return $this->getTrades()->count();
    }
}
