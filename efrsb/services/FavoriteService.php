<?php 

namespace app\efrsb\services;

use app\models\User;
use app\efrsb\models\{Lot, FavoriteUserLotXref};
use app\efrsb\exceptions\FavoriteUserLotException;


class FavoriteService 
{
	
	public static function addFavoriteLot(User $user, Lot $lot)
	{
		# если такая связка уже есть - ничего не делаем. Ошибку не кидаем, просто возвращаемся
		if ($xref = FavoriteUserLotXref::findOne(['userId' => $user->id, 'lotId' => $lot->id])) {
			return;
		}

		$xref = new FavoriteUserLotXref([
			'userId' => $user->id, 
			'lotId' => $lot->id, 
			'tradePlaceInn' => $lot->trade->tradePlace->inn,
			'tradeEtpId' => $lot->trade->etpId,
			'lotNumber' => $lot->lotNumber,
		]);

		if (!$xref->save()) {
			throw new FavoriteUserLotException('Не удалось сохранить связку пользователь-лот. Ошибки: ' . implode(', ', $xref->getErrorSummary(true)));
		}
	}


	public static function removeFavoriteLot(User $user, Lot $lot)
	{
		# если такой связки нет - ничего не делаем. Ошибку не кидаем, просто возвращаемся
		if (!$xref = FavoriteUserLotXref::findOne(['userId' => $user->id, 'lotId' => $lot->id])) {
			return;
		}

		if (!$xref->delete()) {
			throw new FavoriteUserLotException('Не удалось удалить связку пользователь-лот.');
		}
	}


	public static function getFavoriteLotsByUserQuery(User $user)
	{
		return Lot::find()
			->with('trade')
			->innerJoin(FavoriteUserLotXref::tableName() . ' xref', 'efrsb_lot.id = xref.lotId')
			->andWhere(['xref.userId' => $user->id]);
	}
}