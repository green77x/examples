<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "efrsb_company".
 *
 * @property int $id
 * @property string $fullName
 * @property string $shortName
 * @property string $INN
 * @property string $OGRN
 * @property int $isDebtor
 * @property int $isArbitrManager
 * @property int $isTradeOrganizer
 *
 * @property EfrsbTrade[] $efrsbTrades
 * @property EfrsbTrade[] $efrsbTrades0
 * @property EfrsbTrade[] $efrsbTrades1
 */
class Company extends \app\models\ActiveRecord
{
    const TYPE_YES = 1;
    const TYPE_NO = 0;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efrsb_company';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['fullName', 'shortName', 'INN', 'OGRN'], 'required'],
            [['isDebtor', 'isArbitrManager', 'isTradeOrganizer'], 'integer'],
            [['fullName'], 'string', 'max' => 512],
            [['shortName'], 'string', 'max' => 255],
            [['INN'], 'string', 'max' => 10],
            [['OGRN'], 'string', 'max' => 13],
            [['INN'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'fullName' => 'Полное наименование',
            'shortName' => 'Сокращенное наименование',
            'INN' => 'ИНН',
            'OGRN' => 'ОГРН',
            'isDebtor' => 'Is Debtor',
            'isArbitrManager' => 'Is Arbitr Manager',
            'isTradeOrganizer' => 'Is Trade Organizer',
            'typeText' => 'Категория',
            'allTradesCount' => 'Торгов всего',
        ];
    }


    public function getTypeText()
    {
        $array = [];
        if ($this->isDebtor == self::TYPE_YES) {
            $array[] = 'должник';
        }
        if ($this->isArbitrManager == self::TYPE_YES) {
            $array[] = 'арбитражный управляющий';
        }
        if ($this->isTradeOrganizer == self::TYPE_YES) {
            $array[] = 'организатор торгов';
        }

        return implode(', ', $array);
    }


    public function getAllTradesQuery()
    {
        $id = $this->id;
        $query = Trade::find()
            ->where(['or',
                "debtorPersonId = $id",
                "arbitrManagerPersonId = $id",
                "tradeOrganizerPersonId = $id",
            ]
        );

        return $query;
    }


    public function getAllTradesCount()
    {
        return $this->getAllTradesQuery()->count();
    }


    public function getDebtorTrades()
    {
        return $this->hasMany(Trade::class, ['debtorPersonId' => 'id']);
    }


    public function getArbitrManagerTrades()
    {
        return $this->hasMany(Trade::class, ['arbitrManagerPersonId' => 'id']);
    }


    public function getTradeOrganizerTrades()
    {
        return $this->hasMany(Trade::class, ['tradeOrganizerPersonId' => 'id']);
    }


    public function getTradeCountText()
    {
        $d = $this->getDebtorTrades()->count();
        $au = $this->getArbitrManagerTrades()->count();
        $to = $this->getTradeOrganizerTrades()->count();
        return "$d/$au/$to";
    }
}
