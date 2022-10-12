<?php

namespace app\efrsb\models;

use app\efrsb\exceptions\WrongMessageOrderException;
use app\models\User;
use app\efrsb\services\ClassificationService;
use Yii;
use yii\helpers\{Html, Url};

/**
 * This is the model class for table "efrsb_lot".
 *
 * @property int $id
 * @property int $tradeId
 * @property int $lotNumber
 * @property double $startPrice
 * @property string $tradeObjectHtml
 * @property string $paymentInfo
 * @property string $saleAgreement
 * @property int $classificationId
 *
 * @property EfrsbTrade $trade
 */
class Lot extends \app\models\ActiveRecord
{
    const PROCESSED_NO = 0;             // не обрабатывался
    const PROCESSED_OK = 1;             // обработан, найден ровно 1 бренд и 1 модель
    // const PROCESSED_FAILED_MODEL = 2;   // обработан, найден 1 бренд, но модель не найдена (или найдено больше одной)
    // const PROCESSED_FAILED_BRAND = 3;    // обработан, но бренд не найден (или найдено больше одного)
    const PROCESSED_FAILED = 4;     // обработан, но возникли проблемы
    const PROCESSED_MANUALLY = 5;


    const RESULT_SUCCESS = 'Success';
    const RESULT_FAILURE = 'Failure';


    public static function tableName()
    {
        return 'efrsb_lot';
    }


    public function rules()
    {
        return [
            [['tradeId', 'lotNumber', 'tradeObjectHtml'], 'required'],
           [['tradeId', 'lotNumber', 'processedStatus'], 'integer'],
           [['actualPrice', 'startPrice', 'stepPrice', 'stepPricePercent', 'advance', 'advancePercent'], 'number'],
           [['tradeObjectHtml', 'priceReduction', 'concours', 'participants', 'paymentInfo', 'saleAgreement', 'status', 'previousStatus', 'result', 'messagesHistory'], 'string'],
           [['dateCreate', 'dateUpdate'], 'safe'],
           [['classificationId'], 'string', 'max' => 255],
           [['tradeId'], 'exist', 'skipOnError' => true, 'targetClass' => Trade::className(), 'targetAttribute' => ['tradeId' => 'id']],
        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tradeId' => 'ID торгов',
            'lotNumber' => 'Номер лота',
            'startPrice' => 'Начальная цена',
            'actualPrice' => 'Текущая цена',
            'tradeObjectHtml' => 'Описание',
            'paymentInfo' => 'Оплата',
            'saleAgreement' => 'Условия покупки',
            'classificationId' => 'Категория',
            'classificationText' => 'Категория',
            'regionText' => 'Регион должника',
            'tradeHref' => 'Торги',
            'status' => 'Статус',
            'statusText' => 'Статус',
            'stepPrice' => 'Шаг аукциона (руб)',
            'stepPricePercent' => 'Шаг аукциона (%)',
            'priceReduction' => 'Информация о снижении цены',
            'advance' => 'Задаток (руб)',
            'advancePercent' => 'Задаток (%)',
            'concours' => 'Условия проведения открытых и закрытых торгов в форме конкурса',
            'participants' => 'Требования к участникам в случае проведения торгов, закрытых по составу участников',
            'result' => 'Итоги торгов по лоту',
        ];
    }


    public function getTrade()
    {
        return $this->hasOne(Trade::className(), ['id' => 'tradeId']);
    }


    public function getProcessedStatusText(): string
    {
        switch ($this->processedStatus) {
            case self::PROCESSED_NO: return 'не обрабатывался';
            case self::PROCESSED_OK: return 'успешно обработан';
            case self::PROCESSED_FAILED: return 'проблема при обработке';
            case self::PROCESSED_MANUALLY: return 'обработан вручную';
        }
    }


    public function getPossibleBrandsAndModelsText()
    {
        $array = [];

        foreach ($this->possibleBrands as $possibleBrand) {
            $brandTitle = $possibleBrand->brand->title;
            $possibleModels = $possibleBrand->possibleModels;

            if (count($possibleModels) > 0) {
                foreach ($possibleModels as $possibleModel) {
                    $array[] = $brandTitle . ' ' . $possibleModel->model->title;
                }
            } else {
                $array[] = $brandTitle;
            }
        }

        return implode('; ', $array);
    }


    public function getPossibleBrands()
    {
        return $this->hasMany(PossibleBrand::className(), ['lotId' => 'id']);
    }


    public function deleteExistingPossibleBrandsModels()
    {
        foreach ($this->possibleBrands as $possibleBrand) {
            $possibleBrand->delete();
        }
        $this->processedStatus = self::PROCESSED_NO;
        $this->save();
    }


    public function getRegionText()
    {
        return $this->trade->regionText;
    }


    public function getClassificationText()
    {
        return ClassificationService::getTextByCode($this->classificationId, true);
    }


    // public function getTradeHref()
    // {
    //     return Html::a('Ссылка на торги', Url::to(['efrsb/trade/view', 'id' => $this->tradeId]));
    // }


    public static function generateResultColumnSchema()
    {
        $array = self::getResultsArray();
        foreach ($array as $key => $el) {
            $array[$key] = '\'' . $el . '\'';
        }

        return "enum(" . implode(', ', $array) . ")";
    }


    public static function getResultsArray()
    {
        return [
            self::RESULT_SUCCESS,
            self::RESULT_FAILURE,
        ];
    }


    public function getStatusText()
    {
        return Status::getTextByStatus($this->status);
    }


    public function getFavoriteUsers()
    {
        return $this->hasMany(User::className(), ['id' => 'userId'])
            ->viaTable(FavoriteUserLotXref::tableName(), ['lotId' => 'id']);
    }


    public function addNewMessageToHistory(int $messageId)
    {
        if (empty($this->messagesHistory)) {
            $this->messagesHistory = (string)$messageId;
            return;
        }

        // $array = explode(',', $this->messagesHistory);
        $lastMessageId = $this->getLastMessageId();
        if ($messageId <= $lastMessageId) {
            throw new WrongMessageOrderException('Нельзя добавить сообщение, которое не является крайним для лота');
        }

        $this->messagesHistory .= ",$messageId";

    }


    public function getLastMessageId()
    {
        // $array = explode(',', $this->messagesHistory);
        // return $array[count($array)-1];
        $commaPosition = strrchr($this->messagesHistory, ',');
        if (false === $commaPosition) {
            return $this->messagesHistory;
        }

        return substr($commaPosition, 1);
    }

}
