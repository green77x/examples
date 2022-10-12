<?php

namespace app\efrsb\models;

use Yii;

/**
 * This is the model class for table "efrsb_person".
 *
 * @property int $id
 * @property string $firstName
 * @property string $middleName
 * @property string $lastName
 * @property string $INN
 * @property string $SNILS
 * @property int $isDebtor
 * @property int $isArbitrManager
 * @property int $isTradeOrganizer
 * @property int $arbitrSroId
 * @property string $arbitrRegNum
 *
 * @property EfrsbSro $arbitrSro
 */
class Person extends \app\models\ActiveRecord
{

    const TYPE_YES = 1;
    const TYPE_NO = 0;


    public static function tableName()
    {
        return 'efrsb_person';
    }


    public function rules()
    {
        return [
            [['firstName', 'lastName'], 'required'],
            [['isDebtor', 'isArbitrManager', 'isTradeOrganizer'], 'integer'],
            [['firstName', 'middleName', 'lastName'], 'string', 'max' => 50],
            [['INN'], 'string', 'max' => 12],
            [['SNILS'], 'string', 'max' => 11],
            [['arbitrRegNum'], 'string', 'max' => 30],
            ['arbitrSROName', 'string', 'max' => 512],
            [['INN'], 'unique'],
        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'firstName' => 'Имя',
            'middleName' => 'Отчество',
            'lastName' => 'Фамилия',
            'INN' => 'ИНН',
            'SNILS' => 'СНИЛС',
            'isDebtor' => 'Is Debtor',
            'isArbitrManager' => 'Is Arbitr Manager',
            'isTradeOrganizer' => 'Is Trade Organizer',
            'arbitrRegNum' => 'Регистрационный номер ФРС (для арбитражных управляющих)',
            'arbitrSROName' => 'Наименование СРО (для арбитражных управляющих)',
            'typeText' => 'Категория',
            'fullName' => 'ФИО',
            'tradeCountText' => 'Кол-во торгов (должник/АУ/ОТ)',
            'allTradesCount' => 'Торгов всего',
        ];
    }


    // public function getTrades()
    // {
    //     return $this->hasMany(Trade::class, [
    //         'or', [
    //             ['debtorPersonId' => 'id'],
    //             ['arbitrManagerPersonId' => 'id'],
    //             ['tradeOrganizerPersonId' => 'id'],
    //         ]
    //     ]);
    // }


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


    public function getInitials()
    {
        return $this->lastName . ' ' . mb_substr($this->firstName, 0, 1) . '.' . mb_substr($this->middleName, 0, 1) . '.';
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


    public function getFullName()
    {
        return "$this->lastName $this->firstName $this->middleName";
    }


    public function getTradeCountText()
    {
        $d = $this->getDebtorTrades()->count();
        $au = $this->getArbitrManagerTrades()->count();
        $to = $this->getTradeOrganizerTrades()->count();
        return "$d/$au/$to";
    }
}
