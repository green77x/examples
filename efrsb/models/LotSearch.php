<?php

namespace app\efrsb\models;

use yii\data\ActiveDataProvider;


class LotSearch extends \yii\base\Model
{

	public $cat;
	public $rc;
    public $minPr;
    public $maxPr;
    public $st;
    public $minActPr;
    public $maxActPr;
    public $appBegin;
    public $appEnd;
    public $tradeBegin;
    public $tradeEnd;


	public function rules()
    {
        return [
            [['cat', 'rc', 'minPr', 'maxPr', 'st', 'minActPr', 'maxActPr', 'appBegin', 'appEnd', 'tradeBegin', 'tradeEnd'], 'safe'],
            [['minPr', 'maxPr', 'minActPr', 'maxActPr'], 'number', 'enableClientValidation' => false],
        ];
    }


    public function beforeValidate()
    {
        $this->minPr = str_replace(' ', '', $this->minPr);
        $this->minPr = str_replace(',', '.', $this->minPr);
        $this->maxPr = str_replace(' ', '', $this->maxPr);
        $this->maxPr = str_replace(',', '.', $this->maxPr);
        return parent::beforeValidate();
    }


    public function attributeLabels()
    {
        return [
        	'cat' => 'Категория лота',
        	'rc' => 'Регион должника',
            'minPr' => 'Минимальная начальная цена',
            'maxPr' => 'Максимальная начальная цена',
            'minActPr' => 'Минимальная текущая цена',
            'maxActPr' => 'Максимальная текущая цена',
            'st' => 'Статус лота',
        ];
    }


    public function formName()
    {
    	return 'ls';
    }


    public function search($params)
    {
        $query = Lot::find()->with(['trade', 'favoriteUsers']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        # категория
        $query->andFilterWhere(['classificationId' => $this->cat]);

        # регион
        if ((is_array($this->rc)) and (count($this->rc) > 0)) {
            $query->innerJoinWith('trade');
            $query->andFilterWhere(['regionCode' => $this->rc]);
        }

        # мин. начальная цена
        $query->andFilterWhere(['>=', 'startPrice', $this->minPr]);

        # макс. начальная цена
        $query->andFilterWhere(['<=', 'startPrice', $this->maxPr]);

        # мин. текущая цена
        $query->andFilterWhere(['>=', 'actualPrice', $this->minActPr]);

        # макс. текущая цена
        $query->andFilterWhere(['<=', 'actualPrice', $this->maxActPr]);

        # статус
        $query->andFilterWhere(['efrsb_lot.status' => $this->st]);


        # период приема заявок
        if (isset($this->appBegin) && !empty($this->appBegin) &&
    		isset($this->appEnd) && !empty($this->appEnd)
    	) {
            $query->innerJoinWith('trade');
            $appBegin = $this->appBegin . ' 00:00:00';
            $appEnd = $this->appEnd . ' 23:59:59';
            $exprBegin = new \yii\db\Expression("STR_TO_DATE('$appBegin', '%Y-%m-%d %H:%i:%s')");
            $exprEnd = new \yii\db\Expression("STR_TO_DATE('$appEnd', '%Y-%m-%d %H:%i:%s')");

            $query->andWhere([
            	'or',
            	['between', 'applicationTimeBegin', $exprBegin, $exprEnd],
            	['between', 'applicationTimeEnd', $exprBegin, $exprEnd],
            ]);
        }


        # период проведения торгов
        if (isset($this->tradeBegin) && !empty($this->tradeBegin) &&
    		isset($this->tradeEnd) && !empty($this->tradeEnd)
    	) {
            $query->innerJoinWith('trade');
            $tradeBegin = $this->tradeBegin . ' 00:00:00';
            $tradeEnd = $this->tradeEnd . ' 23:59:59';
            $exprBegin = new \yii\db\Expression("STR_TO_DATE('$tradeBegin', '%Y-%m-%d %H:%i:%s')");
            $exprEnd = new \yii\db\Expression("STR_TO_DATE('$tradeEnd', '%Y-%m-%d %H:%i:%s')");

            $query->andWhere(
            	['or',
            		['and',
            			['formPrice' => Trade::FORM_PRICE_OPEN_FORM],
            			['or',
            				['between', 'openFormTimeBegin', $exprBegin, $exprEnd],
            				['between', 'openFormTimeEnd', $exprBegin, $exprEnd],
            			],
            		],
            		['and',
            			['formPrice' => Trade::FORM_PRICE_CLOSE_FORM],
            			['between', 'closeFormTimeResult', $exprBegin, $exprEnd],
            		],
            ]);
        }


        return $dataProvider;
    }
}
