<?
use Bitrix\Main,
	Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale;

Loc::loadMessages(__FILE__);

class CAllSaleDiscount
{
	const VERSION_OLD = Sale\Internals\DiscountTable::VERSION_OLD;
	const VERSION_NEW = Sale\Internals\DiscountTable::VERSION_NEW;
	const VERSION_15 = Sale\Internals\DiscountTable::VERSION_15;

	const OLD_DSC_TYPE_PERCENT = 'P';
	const OLD_DSC_TYPE_FIX = 'V';

	const PREPARE_CONDITIONS = 1;
	const PREPARE_ACTIONS = 2;

	static protected $cacheDiscountHandlers = array();
	static protected $usedModules = array();

	public static function DoProcessOrder(
		&$arOrder,
		/** @noinspection PhpUnusedParameterInspection */$arOptions,
		/** @noinspection PhpUnusedParameterInspection */&$arErrors
	)
	{
		if (empty($arOrder['BASKET_ITEMS']) || !is_array($arOrder['BASKET_ITEMS']))
			return;

		$isOrderConverted = \Bitrix\Main\Config\Option::get("main", "~sale_converted_15", 'N');
		$onlySaleDiscounts = (string)Main\Config\Option::get('sale', 'use_sale_discount_only') == 'Y';
		$oldDelivery = '';

		$checkIds = true;
		$arIDS = array();
		if ($isOrderConverted == 'Y')
		{
			if (isset($arOrder['DELIVERY_ID']) && $arOrder['DELIVERY_ID'] != '')
			{
				$oldDelivery = $arOrder['DELIVERY_ID'];
				$arOrder['DELIVERY_ID'] = \CSaleDelivery::getIdByCode($arOrder['DELIVERY_ID']);
			}
			$adminSection = (defined('ADMIN_SECTION') && ADMIN_SECTION === true);
			if ($adminSection)
			{
				$mode = Sale\Compatible\DiscountCompatibility::MODE_MANAGER;
				$modeParams = array();
				if (isset($arOrder['CURRENCY']))
					$modeParams['CURRENCY'] = $arOrder['CURRENCY'];
				if (isset($arOrder['SITE_ID']))
				{
					$modeParams['SITE_ID'] = $arOrder['SITE_ID'];
					if (!isset($modeParams['CURRENCY']))
						$modeParams['CURRENCY'] = Sale\Internals\SiteCurrencyTable::getSiteCurrency($modeParams['SITE_ID']);
				}
			}
			else
			{
				$mode = Sale\Compatible\DiscountCompatibility::MODE_CLIENT;
				$modeParams = array(
					'SITE_ID' => SITE_ID,
					'CURRENCY' => Sale\Internals\SiteCurrencyTable::getSiteCurrency(SITE_ID)
				);

				$basketIdList = array();
				foreach ($arOrder['BASKET_ITEMS'] as $basketId => $basketItem)
				{
					if (!isset($basketItem['PRODUCT_PRICE_ID']) && isset($basketItem['ID']))
					{
						$basketIdList[$basketItem['ID']] = $basketId;
					}
				}
				unset($basketId, $basketItem);
				if (!empty($basketIdList))
				{
					$iterator = Sale\Internals\BasketTable::getList(array(
						'select' => array('ID', 'PRODUCT_PRICE_ID'),
						'filter' => array('@ID' => array_keys($basketIdList))
					));
					while ($row = $iterator->fetch())
					{
						if (!isset($basketIdList[$row['ID']]))
							continue;
						$index = $basketIdList[$row['ID']];
						$arOrder['BASKET_ITEMS'][$index]['PRODUCT_PRICE_ID'] = $row['PRODUCT_PRICE_ID'];
						unset($index);
					}
					unset($row, $iterator);
				}
			}
			unset($adminSection);
			if (!empty($modeParams))
			{
				Sale\Discount\Actions::setUseMode(
					Sale\Discount\Actions::MODE_CALCULATE,
					array(
						'USE_BASE_PRICE' => \Bitrix\Main\Config\Option::get('sale', 'get_discount_percent_from_base_price'),
						'SITE_ID' => $modeParams['SITE_ID'],
						'CURRENCY' => $modeParams['CURRENCY']
					)
				);
			}
			if (!Sale\Compatible\DiscountCompatibility::isInited())
			{
				if (!empty($modeParams))
					Sale\Compatible\DiscountCompatibility::init($mode, $modeParams);
			}
			unset($modeParams, $mode);
			Sale\Compatible\DiscountCompatibility::clearDiscountResult();
			Sale\Compatible\DiscountCompatibility::fillBasketData($arOrder['BASKET_ITEMS']);
			Sale\Compatible\DiscountCompatibility::calculateBasketDiscounts($arOrder['BASKET_ITEMS']);
			Sale\Compatible\DiscountCompatibility::roundPrices($arOrder['BASKET_ITEMS']);
			Sale\Compatible\DiscountCompatibility::setApplyMode($arOrder['BASKET_ITEMS']);

			$applyMode = Sale\Discount::getApplyMode();
			if ($applyMode == Sale\Discount::APPLY_MODE_FULL_LAST || $applyMode == Sale\Discount::APPLY_MODE_FULL_DISABLE)
			{
				foreach ($arOrder['BASKET_ITEMS'] as &$basketItem)
				{
					if (isset($basketItem['LAST_DISCOUNT']) && $basketItem['LAST_DISCOUNT'] == 'Y')
					{
						$checkIds = false;
						break;
					}
				}
				unset($basketItem);
			}
		}

		if ($checkIds)
		{
			$groupDiscountIterator = Sale\Internals\DiscountGroupTable::getList(array(
				'select' => array('DISCOUNT_ID'),
				'filter' => array('@GROUP_ID' => CUser::GetUserGroup($arOrder['USER_ID']), '=ACTIVE' => 'Y')
			));
			while ($groupDiscount = $groupDiscountIterator->fetch())
			{
				$groupDiscount['DISCOUNT_ID'] = (int)$groupDiscount['DISCOUNT_ID'];
				if ($groupDiscount['DISCOUNT_ID'] > 0)
					$arIDS[$groupDiscount['DISCOUNT_ID']] = true;
			}
		}
		if (!empty($arIDS))
		{
			$arIDS = array_keys($arIDS);
			$couponList = Sale\DiscountCouponsManager::getForApply(array('MODULE_ID' => 'sale', 'DISCOUNT_ID' => $arIDS), array(), true);

			//TODO: fix this condition
			$useProps = true;
			$iblockPropList = array();
			$entityList = Sale\Internals\DiscountEntitiesTable::getByDiscount(
				$arIDS,
				array(
					'=MODULE_ID' => 'catalog',
					'@ENTITY' => array(
						'ELEMENT_PROPERTY', 'PRICE'
					)
				)
			);
			if (empty($entityList))
			{
				$useProps = false;
			}
			else
			{
				if (empty($entityList['catalog']['ELEMENT_PROPERTY']))
				{
					$useProps = false;
				}
				else
				{
					foreach ($entityList['catalog']['ELEMENT_PROPERTY'] as $entity)
					{
						$entityField = explode(':', $entity['FIELD_TABLE']);
						if (isset($entityField[1]))
						{
							$propId = (int)$entityField[1];
							if ($propId > 0)
								$iblockPropList[$propId] = $propId;
							unset($propId);
						}
						unset($entityField);
					}
					unset($entity);
					if (empty($iblockPropList))
						$useProps = false;
				}
			}

			$arExtend = array(
				'catalog' => array(
					'fields' => true,
					'price' => !empty($entityList['catalog']['PRICE']),
					'props' => $useProps,
				),
			);
			if ($useProps)
				$arExtend['iblock']['props'] = $iblockPropList;
			unset($iblockPropList, $useProps);
			foreach (GetModuleEvents('sale', 'OnExtendBasketItems', true) as $arEvent)
				ExecuteModuleEventEx($arEvent, array(&$arOrder['BASKET_ITEMS'], $arExtend));

			foreach ($arOrder['BASKET_ITEMS'] as &$arOneItem)
			{
				if (
					array_key_exists('PRODUCT_PROVIDER_CLASS', $arOneItem) && empty($arOneItem['PRODUCT_PROVIDER_CLASS'])
					&& array_key_exists('CALLBACK_FUNC', $arOneItem) && empty($arOneItem['CALLBACK_FUNC'])
					&& (!isset($arOneItem['CUSTOM_PRICE']) || $arOneItem['CUSTOM_PRICE'] != 'Y')
				)
				{
					if (isset($arOneItem['DISCOUNT_PRICE']))
					{
						$arOneItem['PRICE'] += $arOneItem['DISCOUNT_PRICE'];
						$arOneItem['DISCOUNT_PRICE'] = 0;
						$arOneItem['BASE_PRICE'] = $arOneItem['PRICE'];
					}
				}
			}
			if (isset($arOneItem))
				unset($arOneItem);

			if (empty(self::$cacheDiscountHandlers))
			{
				self::$cacheDiscountHandlers = CSaleDiscount::getDiscountHandlers($arIDS);
			}
			else
			{
				$needDiscountHandlers = array();
				foreach ($arIDS as &$discountID)
				{
					if (!isset(self::$cacheDiscountHandlers[$discountID]))
						$needDiscountHandlers[] = $discountID;
				}
				unset($discountID);
				if (!empty($needDiscountHandlers))
				{
					$discountHandlersList = CSaleDiscount::getDiscountHandlers($needDiscountHandlers);
					if (!empty($discountHandlersList))
					{
						foreach ($discountHandlersList as $discountID => $discountHandlers)
						{
							self::$cacheDiscountHandlers[$discountID] = $discountHandlers;
						}
						unset($discountHandlers, $discountID);
					}
					unset($discountHandlersList);
				}
				unset($needDiscountHandlers);
			}

			$currentDatetime = new Main\Type\DateTime();
			$discountSelect = array(
				'ID', 'PRIORITY', 'SORT', 'LAST_DISCOUNT', 'LAST_LEVEL_DISCOUNT', 'UNPACK', 'APPLICATION', 'USE_COUPONS', 'EXECUTE_MODULE',
				'NAME', 'CONDITIONS_LIST', 'ACTIONS_LIST'
			);
			$discountOrder = array('PRIORITY' => 'DESC', 'SORT' => 'ASC', 'ID' => 'ASC');
			$discountFilter = array(
				'@ID' => $arIDS,
				'=LID' => $arOrder['SITE_ID'],
				array(
					'LOGIC' => 'OR',
					'ACTIVE_FROM' => '',
					'<=ACTIVE_FROM' => $currentDatetime
				),
				array(
					'LOGIC' => 'OR',
					'ACTIVE_TO' => '',
					'>=ACTIVE_TO' => $currentDatetime
				)
			);
			if (empty($couponList))
			{
				$discountFilter['=USE_COUPONS'] = 'N';
			}
			else
			{
				$discountFilter[] = array(
					'LOGIC' => 'OR',
					'=USE_COUPONS' => 'N',
					array(
						'=USE_COUPONS' => 'Y',
						'=COUPON.COUPON' => array_keys($couponList)
					)
				);
				$discountSelect['DISCOUNT_COUPON'] = 'COUPON.COUPON';
			}

			$newDiscounts = null;
			$resultDiscountFullList = array();
			$discountIterator = Sale\Internals\DiscountTable::getList(array(
				'select' => $discountSelect,
				'filter' => $discountFilter,
				'order' => $discountOrder
			));

			$discountApply = array();
			$resultDiscountList = array();
			$resultDiscountKeys = array();
			$resultDiscountIndex = 0;
			$skipPriorityLevel = null;
			while ($discount = $discountIterator->fetch())
			{
				$discount['ID'] = (int)$discount['ID'];
				if (isset($discountApply[$discount['ID']]))
					continue;
				$discountApply[$discount['ID']] = true;

				if($skipPriorityLevel == $discount['PRIORITY'])
				{
					continue;
				}
				$skipPriorityLevel = null;

				static::prefillDiscountFields($discount, $couponList);
				$applyFlag = static::workWithDiscountHandlers($discount);

				if ($isOrderConverted == 'Y')
					Sale\Compatible\DiscountCompatibility::setOrderData($arOrder);
				if ($applyFlag && self::__Unpack($arOrder, $discount['UNPACK']))
				{
					$oldOrder = $arOrder;
					if ($isOrderConverted == 'Y')
						Sale\Discount\Actions::clearAction();

					self::__ApplyActions($arOrder, $discount['APPLICATION']);

					if ($isOrderConverted == 'Y')
					{
						$resultDiscountFullList[] = $discount;
						if (Sale\Compatible\DiscountCompatibility::calculateSaleDiscount($arOrder, $discount))
						{
							$resultDiscountList[$resultDiscountIndex] = array(
								'MODULE_ID' => $discount['MODULE_ID'],
								'ID' => $discount['ID'],
								'NAME' => $discount['NAME'],
								'PRIORITY' => $discount['PRIORITY'],
								'SORT' => $discount['SORT'],
								'LAST_DISCOUNT' => $discount['LAST_DISCOUNT'],
								'CONDITIONS' => serialize($discount['CONDITIONS_LIST']),
								'UNPACK' => $discount['UNPACK'],
								'ACTIONS' => serialize($discount['ACTIONS_LIST']),
								'APPLICATION' => $discount['APPLICATION'],
								'RESULT' => self::getDiscountResult($oldOrder, $arOrder, false),
								'HANDLERS' => self::$cacheDiscountHandlers[$discount['ID']],
								'USE_COUPONS' => $discount['USE_COUPONS'],
								'COUPON' => ($discount['USE_COUPONS'] == 'Y' ? $couponList[$discount['DISCOUNT_COUPON']] : false)
							);
							$resultDiscountKeys[$discount['ID']] = $resultDiscountIndex;
							$resultDiscountIndex++;
							if ($discount['LAST_DISCOUNT'] == 'Y')
								break;

							if ($discount['LAST_LEVEL_DISCOUNT'] == 'Y')
							{
								$skipPriorityLevel = $discount['PRIORITY'];
							}
						}
						Sale\Discount\Actions::clearAction();
					}
					else
					{
						$discountResult = self::getDiscountResult($oldOrder, $arOrder, false);
						if (!empty($discountResult['DELIVERY']) || !empty($discountResult['BASKET']))
						{
							if ($discount['USE_COUPONS'] == 'Y' && !empty($discount['DISCOUNT_COUPON']))
							{
								if ($couponList[$discount['DISCOUNT_COUPON']]['TYPE'] == Sale\Internals\DiscountCouponTable::TYPE_BASKET_ROW)
									self::changeDiscountResult($oldOrder, $arOrder, $discountResult);
								$couponApply = Sale\DiscountCouponsManager::setApply($discount['DISCOUNT_COUPON'], $discountResult);
								unset($couponApply);
							}
							$resultDiscountList[$resultDiscountIndex] = array(
								'MODULE_ID' => $discount['MODULE_ID'],
								'ID' => $discount['ID'],
								'NAME' => $discount['NAME'],
								'PRIORITY' => $discount['PRIORITY'],
								'SORT' => $discount['SORT'],
								'LAST_DISCOUNT' => $discount['LAST_DISCOUNT'],
								'CONDITIONS' => serialize($discount['CONDITIONS_LIST']),
								'UNPACK' => $discount['UNPACK'],
								'ACTIONS' => serialize($discount['ACTIONS_LIST']),
								'APPLICATION' => $discount['APPLICATION'],
								'RESULT' => $discountResult,
								'HANDLERS' => self::$cacheDiscountHandlers[$discount['ID']],
								'USE_COUPONS' => $discount['USE_COUPONS'],
								'COUPON' => ($discount['USE_COUPONS'] == 'Y' ? $couponList[$discount['DISCOUNT_COUPON']] : false)
							);
							$resultDiscountKeys[$discount['ID']] = $resultDiscountIndex;
							$resultDiscountIndex++;
							if ($discount['LAST_DISCOUNT'] == 'Y')
								break;
						}
						unset($discountResult);
					}
				}
			}
			unset($discount, $discountIterator);

			$arOrder['DISCOUNT_LIST'] = $resultDiscountList;
			$arOrder['FULL_DISCOUNT_LIST'] = $resultDiscountFullList;
			if ($isOrderConverted == 'Y')
				Sale\Compatible\DiscountCompatibility::setOldDiscountResult($resultDiscountList);
		}

		$arOrder["ORDER_PRICE"] = 0;
		$arOrder["ORDER_WEIGHT"] = 0;
		$arOrder["USE_VAT"] = false;
		$arOrder["VAT_RATE"] = 0;
		$arOrder["VAT_SUM"] = 0;
		$arOrder["DISCOUNT_PRICE"] = 0.0;
		$arOrder["DISCOUNT_VALUE"] = $arOrder["DISCOUNT_PRICE"];
		$arOrder["PRICE_DELIVERY"] = roundEx($arOrder["PRICE_DELIVERY"], SALE_VALUE_PRECISION);
		$arOrder["DELIVERY_PRICE"] = $arOrder["PRICE_DELIVERY"];

		foreach ($arOrder['BASKET_ITEMS'] as &$arShoppingCartItem)
		{
			if (isset($arShoppingCartItem['CATALOG']))
				unset($arShoppingCartItem['CATALOG']);
			if (!CSaleBasketHelper::isSetItem($arShoppingCartItem))
			{
				$customPrice = isset($arShoppingCartItem['CUSTOM_PRICE']) && $arShoppingCartItem['CUSTOM_PRICE'] = 'Y';
				if (!$customPrice)
				{
					$arShoppingCartItem['DISCOUNT_PRICE'] = roundEx($arShoppingCartItem['DISCOUNT_PRICE'], SALE_VALUE_PRECISION);
					if ($arShoppingCartItem['DISCOUNT_PRICE'] > 0)
						$arShoppingCartItem['PRICE'] = $arShoppingCartItem['BASE_PRICE'] - $arShoppingCartItem['DISCOUNT_PRICE'];
					else
						$arShoppingCartItem['PRICE'] = roundEx($arShoppingCartItem['PRICE'], SALE_VALUE_PRECISION);
				}
				else
				{
					$arShoppingCartItem['DISCOUNT_PRICE'] = 0;
				}
				if (isset($arShoppingCartItem['VAT_RATE']))
				{
					$vatRate = (float)$arShoppingCartItem['VAT_RATE'];
					if ($vatRate > 0)
						$arShoppingCartItem['VAT_VALUE'] = (($arShoppingCartItem['PRICE'] / ($vatRate + 1)) * $vatRate);
					unset($vatRate);
				}

				$arOrder["ORDER_PRICE"] += $arShoppingCartItem["PRICE"] * $arShoppingCartItem["QUANTITY"];
				$arOrder["ORDER_WEIGHT"] += $arShoppingCartItem["WEIGHT"] * $arShoppingCartItem["QUANTITY"];

				$arShoppingCartItem["PRICE_FORMATED"] = CCurrencyLang::CurrencyFormat($arShoppingCartItem["PRICE"], $arShoppingCartItem["CURRENCY"], true);
				$arShoppingCartItem["DISCOUNT_PRICE_PERCENT"] = 0;
				if ($arShoppingCartItem["DISCOUNT_PRICE"] + $arShoppingCartItem["PRICE"] > 0)
					$arShoppingCartItem["DISCOUNT_PRICE_PERCENT"] = $arShoppingCartItem["DISCOUNT_PRICE"]*100 / ($arShoppingCartItem["DISCOUNT_PRICE"] + $arShoppingCartItem["PRICE"]);
				$arShoppingCartItem["DISCOUNT_PRICE_PERCENT_FORMATED"] = roundEx($arShoppingCartItem["DISCOUNT_PRICE_PERCENT"], SALE_VALUE_PRECISION)."%";

				if ($arShoppingCartItem["VAT_RATE"] > 0)
				{
					$arOrder["USE_VAT"] = true;
					if ($arShoppingCartItem["VAT_RATE"] > $arOrder["VAT_RATE"])
						$arOrder["VAT_RATE"] = $arShoppingCartItem["VAT_RATE"];

					$arOrder["VAT_SUM"] += $arShoppingCartItem["VAT_VALUE"] * $arShoppingCartItem["QUANTITY"];
				}
			}
		}
		unset($arShoppingCartItem);

		if ($isOrderConverted == 'Y' && $oldDelivery != '')
			$arOrder['DELIVERY_ID'] = $oldDelivery;

		$arOrder["ORDER_PRICE"] = roundEx($arOrder["ORDER_PRICE"], SALE_VALUE_PRECISION);
	}

	private static function prefillDiscountFields(array &$discount, array $couponList)
	{
		$discount['MODULE'] = 'sale';
		$discount['MODULE_ID'] = 'sale';
		if($discount['USE_COUPONS'] == 'Y')
		{
			$discount['COUPON'] = $couponList[$discount['DISCOUNT_COUPON']];
		}
	}

	private static function workWithDiscountHandlers(array &$discount)
	{
		$applyFlag = true;
		if (isset(self::$cacheDiscountHandlers[$discount['ID']]))
		{
			$moduleList = self::$cacheDiscountHandlers[$discount['ID']]['MODULES'];
			if (!empty($moduleList))
			{
				foreach ($moduleList as &$moduleID)
				{
					if (!isset(self::$usedModules[$moduleID]))
					{
						self::$usedModules[$moduleID] = Loader::includeModule($moduleID);
					}
					if (!self::$usedModules[$moduleID])
					{
						$applyFlag = false;
						break;
					}
				}
				unset($moduleID);
				if ($applyFlag)
					$discount['MODULES'] = $moduleList;
			}
			unset($moduleList);
		}

		return $applyFlag;
	}

	public function PrepareCurrency4Where($val, $key, $operation, $negative, $field, &$arField, &$arFilter)
	{
		$val = doubleval($val);

		$baseSiteCurrency = "";
		if (isset($arFilter["LID"]) && strlen($arFilter["LID"]) > 0)
			$baseSiteCurrency = CSaleLang::GetLangCurrency($arFilter["LID"]);
		elseif (isset($arFilter["CURRENCY"]) && strlen($arFilter["CURRENCY"]) > 0)
			$baseSiteCurrency = $arFilter["CURRENCY"];

		if (strlen($baseSiteCurrency) <= 0)
			return false;

		$strSqlSearch = "";

		$by = "sort";
		$order = "asc";
		$dbCurrency = CCurrency::GetList($by, $order);
		while ($arCurrency = $dbCurrency->Fetch())
		{
			$val1 = roundEx(CCurrencyRates::ConvertCurrency($val, $baseSiteCurrency, $arCurrency["CURRENCY"]), SALE_VALUE_PRECISION);
			if (strlen($strSqlSearch) > 0)
				$strSqlSearch .= " OR ";

			$strSqlSearch .= "(D.CURRENCY = '".$arCurrency["CURRENCY"]."' AND ";
			if ($negative == "Y")
				$strSqlSearch .= "NOT";
			$strSqlSearch .= "(".$field." ".$operation." ".$val1." OR ".$field." IS NULL OR ".$field." = 0)";
			$strSqlSearch .= ")";
		}

		return "(".$strSqlSearch.")";
	}

	public function GetByID($ID)
	{
		$ID = (int)$ID;
		if ($ID > 0)
		{
			$rsDiscounts = CSaleDiscount::GetList(
				array(),
				array('ID' => $ID),
				false,
				false,
				array(
					"ID",
					"XML_ID",
					"LID",
					"SITE_ID",
					"NAME",
					"PRICE_FROM",
					"PRICE_TO",
					"CURRENCY",
					"DISCOUNT_VALUE",
					"DISCOUNT_TYPE",
					"ACTIVE",
					"SORT",
					"ACTIVE_FROM",
					"ACTIVE_TO",
					"TIMESTAMP_X",
					"MODIFIED_BY",
					"DATE_CREATE",
					"CREATED_BY",
					"PRIORITY",
					"LAST_DISCOUNT",
					"VERSION",
					"CONDITIONS",
					"UNPACK",
					"APPLICATION",
					"ACTIONS",
				)
			);
			if ($arDiscount = $rsDiscounts->Fetch())
			{
				return $arDiscount;
			}
		}
		return false;
	}

	public function CheckFields($ACTION, &$arFields)
	{
		global $DB, $APPLICATION, $USER;

		if (empty($arFields) || !is_array($arFields))
		{
			return false;
		}
		$ACTION = strtoupper($ACTION);
		if ('UPDATE' != $ACTION && 'ADD' != $ACTION)
			return false;

		$discountID = 0;
		if ($ACTION == 'UPDATE')
		{
			if (isset($arFields['ID']))
				$discountID = (int)$arFields['ID'];
			if ($discountID <= 0)
				return false;
		}

		$clearFields = array(
			'ID',
			'~ID',
			'UNPACK',
			'~UNPACK',
			'~CONDITIONS',
			'APPLICATION',
			'~APPLICATION',
			'~ACTIONS',
			'USE_COUPONS',
			'~USE_COUPONS',
			'HANDLERS',
			'~HANDLERS',
			'~VERSION',
			'TIMESTAMP_X',
			'DATE_CREATE',
			'~DATE_CREATE',
			'~MODIFIED_BY',
			'~CREATED_BY',
			'EXECUTE_MODULE',
			'~EXECUTE_MODULE',

		);
		if ($ACTION =='UPDATE')
			$clearFields[] = 'CREATED_BY';

		foreach ($clearFields as &$fieldName)
		{
			if (array_key_exists($fieldName, $arFields))
				unset($arFields[$fieldName]);
		}
		unset($fieldName, $clearFields);

		if ((is_set($arFields, "ACTIVE") || $ACTION=="ADD") && $arFields["ACTIVE"]!="Y")
			$arFields["ACTIVE"] = "N";
		if ((is_set($arFields, "DISCOUNT_TYPE") || $ACTION=="ADD") && $arFields["DISCOUNT_TYPE"] != self::OLD_DSC_TYPE_PERCENT)
			$arFields["DISCOUNT_TYPE"] = self::OLD_DSC_TYPE_FIX;

		if ((is_set($arFields, "SORT") || $ACTION=="ADD") && intval($arFields["SORT"])<=0)
			$arFields["SORT"] = 100;

		if ((is_set($arFields, "LID") || $ACTION=="ADD") && strlen($arFields["LID"])<=0)
			return false;

		if (is_set($arFields, "LID"))
		{
			$dbSite = CSite::GetByID($arFields["LID"]);
			if (!$dbSite->Fetch())
			{
				$APPLICATION->ThrowException(
					Loc::getMessage(
						'SKGD_NO_SITE',
						array('#ID#' => $arFields['LID'])
					),
					'ERROR_NO_SITE'
				);
				return false;
			}
			$arFields['CURRENCY'] = CSaleLang::GetLangCurrency($arFields["LID"]);
		}

		if ((is_set($arFields, "CURRENCY") || $ACTION=="ADD") && strlen($arFields["CURRENCY"])<=0)
			return false;

		if (is_set($arFields, "CURRENCY"))
		{
			if (!($arCurrency = CCurrency::GetByID($arFields["CURRENCY"])))
			{
				$APPLICATION->ThrowException(
					Loc::getMessage(
						'SKGD_NO_CURRENCY',
						array('#ID#' => $arFields['CURRENCY'])
					),
					'ERROR_NO_CURRENCY'
				);
				return false;
			}
		}

		if (is_set($arFields, "DISCOUNT_VALUE") || $ACTION=="ADD")
		{
			if (!is_set($arFields["DISCOUNT_VALUE"]))
				$arFields["DISCOUNT_VALUE"] = '';
			$arFields["DISCOUNT_VALUE"] = str_replace(",", ".", $arFields["DISCOUNT_VALUE"]);
			$arFields["DISCOUNT_VALUE"] = doubleval($arFields["DISCOUNT_VALUE"]);
		}

		if (is_set($arFields, "PRICE_FROM"))
		{
			$arFields["PRICE_FROM"] = str_replace(",", ".", $arFields["PRICE_FROM"]);
			$arFields["PRICE_FROM"] = doubleval($arFields["PRICE_FROM"]);
		}

		if (is_set($arFields, "PRICE_TO"))
		{
			$arFields["PRICE_TO"] = str_replace(",", ".", $arFields["PRICE_TO"]);
			$arFields["PRICE_TO"] = doubleval($arFields["PRICE_TO"]);
		}

		if ((is_set($arFields, "ACTIVE_FROM") || $ACTION=="ADD") && (!$DB->IsDate($arFields["ACTIVE_FROM"], false, LANGUAGE_ID, "FULL")))
			$arFields["ACTIVE_FROM"] = false;
		if ((is_set($arFields, "ACTIVE_TO") || $ACTION=="ADD") && (!$DB->IsDate($arFields["ACTIVE_TO"], false, LANGUAGE_ID, "FULL")))
			$arFields["ACTIVE_TO"] = false;

		if ((is_set($arFields, 'PRIORITY') || $ACTION == 'ADD') && intval($arFields['PRIORITY']) <= 0)
			$arFields['PRIORITY'] = 1;
		if ((is_set($arFields, 'LAST_DISCOUNT') || $ACTION == 'ADD') && $arFields["LAST_DISCOUNT"] != "N")
			$arFields["LAST_DISCOUNT"] = 'Y';

		$arFields['VERSION'] = self::VERSION_15;

		$useConditions = array_key_exists('CONDITIONS', $arFields) || $ACTION == 'ADD';
		$useActions = array_key_exists('ACTIONS', $arFields) || $ACTION == 'ADD';
		$usePredictions = array_key_exists('PREDICTIONS', $arFields) || $ACTION == 'ADD';
		$updateData = $useConditions || $useActions || $usePredictions;
		$discountSite = (isset($arFields['LID']) ? trim($arFields['LID']) : '');
		$usedHandlers = array();
		$usedEntities = array();
		$executeModule = '';
		$conditionData = array(
			'HANDLERS' => array(),
			'ENTITY' => array(),
			'EXECUTE_MODULE' => array()
		);
		$predictionData = array(
			'HANDLERS' => array(),
			'ENTITY' => array(),
			'EXECUTE_MODULE' => array()
		);
		$actionData = array(
			'HANDLERS' => array(),
			'ENTITY' => array(),
			'EXECUTE_MODULE' => array()
		);
		if ($updateData && $discountSite == '')
		{
			$rsDiscounts = CSaleDiscount::GetList(
				array(),
				array('ID' => $discountID),
				false,
				false,
				array('ID', 'LID')
			);
			if ($discountInfo = $rsDiscounts->Fetch())
			{
				$discountSite = $discountInfo['LID'];
			}
			else
			{
				return false;
			}
		}

		if ($useConditions)
		{
			if (!isset($arFields['CONDITIONS']) || empty($arFields['CONDITIONS']))
			{
				$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_EMPTY_CONDITIONS"), "CONDITIONS");
				return false;
			}
			else
			{
				$arFields['UNPACK'] = '';
				if (!self::prepareDiscountConditions($arFields['CONDITIONS'], $arFields['UNPACK'], $conditionData, self::PREPARE_CONDITIONS, $discountSite))
				{
					return false;
				}
			}
		}

		if ($usePredictions && $arFields['PREDICTIONS'])
		{
			$arFields['PREDICTIONS_APP'] = '';
			if (!self::prepareDiscountConditions($arFields['PREDICTIONS'], $arFields['PREDICTIONS_APP'], $predictionData, self::PREPARE_CONDITIONS, $discountSite))
			{
				return false;
			}
		}

		if ($useActions)
		{
			if (!isset($arFields['ACTIONS']) || empty($arFields['ACTIONS']))
			{
				$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_EMPTY_ACTIONS_EXT"), "ACTIONS");
				return false;
			}
			else
			{
				$arFields['APPLICATION'] = '';
				if (!self::prepareDiscountConditions($arFields['ACTIONS'], $arFields['APPLICATION'], $actionData, self::PREPARE_ACTIONS, $discountSite))
				{
					return false;
				}
			}
		}

		if ($updateData)
		{
			if (!$useConditions)
			{
				$rsDiscounts = CSaleDiscount::GetList(
					array(),
					array('ID' => $discountID),
					false,
					false,
					array('ID', 'CONDITIONS', 'LID')
				);
				if ($discountInfo = $rsDiscounts->Fetch())
				{
					$discountInfo['UNPACK'] = '';
					if (!self::prepareDiscountConditions($discountInfo['CONDITIONS'], $discountInfo['UNPACK'], $conditionData, self::PREPARE_CONDITIONS, $discountInfo['LID']))
					{
						return false;
					}
				}
				else
				{
					return false;
				}
			}
			if (!$usePredictions)
			{
				$rsDiscounts = CSaleDiscount::GetList(
					array(),
					array('ID' => $discountID),
					false,
					false,
					array('ID', 'PREDICTIONS', 'LID')
				);
				if ($discountInfo = $rsDiscounts->Fetch() && $discountInfo['PREDICTIONS'])
				{
					$discountInfo['PREDICTIONS_APP'] = '';
					if (!self::prepareDiscountConditions($discountInfo['PREDICTIONS'], $discountInfo['PREDICTIONS_APP'], $predictionData, self::PREPARE_CONDITIONS, $discountInfo['LID']))
					{
						return false;
					}
				}
			}
			if (!$useActions)
			{
				$rsDiscounts = CSaleDiscount::GetList(
					array(),
					array('ID' => $discountID),
					false,
					false,
					array('ID', 'ACTIONS', 'LID')
				);
				if ($discountInfo = $rsDiscounts->Fetch())
				{
					$discountInfo['APPLICATION'] = '';
					if (!self::prepareDiscountConditions($discountInfo['ACTIONS'], $discountInfo['APPLICATION'], $actionData, self::PREPARE_ACTIONS, $discountInfo['LID']))
					{
						return false;
					}
				}
				else
				{
					return false;
				}
			}
			if (!empty($conditionData['HANDLERS']) || !empty($actionData['HANDLERS']) || !empty($predictionData['HANDLERS']))
			{
				$conditionData['HANDLERS']['MODULES'] = $conditionData['HANDLERS']['MODULES']?: array();
				$actionData['HANDLERS']['MODULES'] = $actionData['HANDLERS']['MODULES']?: array();
				$predictionData['HANDLERS']['MODULES'] = $predictionData['HANDLERS']['MODULES']?: array();

				$conditionData['HANDLERS']['EXT_FILES'] = $conditionData['HANDLERS']['EXT_FILES']?: array();
				$actionData['HANDLERS']['EXT_FILES'] = $actionData['HANDLERS']['EXT_FILES']?: array();
				$predictionData['HANDLERS']['EXT_FILES'] = $predictionData['HANDLERS']['EXT_FILES']?: array();

				$usedHandlers = array(
					'MODULES' => array_unique(array_merge(
						$conditionData['HANDLERS']['MODULES'],
						$actionData['HANDLERS']['MODULES'],
						$predictionData['HANDLERS']['MODULES']
					)),
					'EXT_FILES' => array_unique(array_merge(
						$conditionData['HANDLERS']['EXT_FILES'],
						$actionData['HANDLERS']['EXT_FILES'],
						$predictionData['HANDLERS']['EXT_FILES']
					)),
				);
			}

			if (!empty($conditionData['EXECUTE_MODULE']) || !empty($actionData['EXECUTE_MODULE']) || !empty($predictionData['EXECUTE_MODULE']))
			{
				$conditionData['EXECUTE_MODULE'] = $conditionData['EXECUTE_MODULE']?: array();
				$actionData['EXECUTE_MODULE'] = $actionData['EXECUTE_MODULE']?: array();
				$predictionData['EXECUTE_MODULE'] = $predictionData['EXECUTE_MODULE']?: array();

				$executeModuleList = array_merge(
					$conditionData['EXECUTE_MODULE'],
					$actionData['EXECUTE_MODULE'],
					$predictionData['EXECUTE_MODULE']
				);

				$executeModuleList = array_unique($executeModuleList);
				if (count($executeModuleList) > 1)
				{
					$APPLICATION->ThrowException(Loc::getMessage('BX_SALE_DISC_ERR_MULTIPLE_EXECUTE_MODULE'), 'DISCOUNT');
					return false;
				}
				$executeModule = current($executeModuleList);
				unset($executeModuleList);
			}

			if (!empty($conditionData['ENTITY']) || !empty($actionData['ENTITY']) || !empty($predictionData['ENTITY']))
			{
				$conditionData['ENTITY'] = $conditionData['ENTITY']?: array();
				$actionData['ENTITY'] = $actionData['ENTITY']?: array();
				$predictionData['ENTITY'] = $predictionData['ENTITY']?: array();

				$usedEntities = array_merge(
					$conditionData['ENTITY'],
					$actionData['ENTITY'],
					$predictionData['ENTITY']
				);
			}
		}
		if (($ACTION == 'ADD' || $updateData) && $executeModule == '')
			$executeModule = 'all';
		if ($executeModule != '')
			$arFields['EXECUTE_MODULE'] = $executeModule;

		if (!empty($usedHandlers))
			$arFields['HANDLERS'] = $usedHandlers;
		if (!empty($usedEntities))
			$arFields['ENTITIES'] = $usedEntities;

		if ((is_set($arFields, 'USE_COUPONS') || $ACTION == 'ADD') && ('Y' != $arFields['USE_COUPONS']))
			$arFields['USE_COUPONS'] = 'N';

		if (array_key_exists('USER_GROUPS', $arFields) || $ACTION=="ADD")
		{
			Main\Type\Collection::normalizeArrayValuesByInt($arFields['USER_GROUPS']);
			if (empty($arFields['USER_GROUPS']) || !is_array($arFields['USER_GROUPS']))
			{
				$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_USER_GROUPS_ABSENT_SHORT"), "USER_GROUPS");
				return false;
			}
		}

		if(empty($arFields['SHORT_DESCRIPTION']) && !empty($arFields['ACTIONS']))
		{
			$actionConfiguration = Sale\Discount\Actions::getActionConfiguration($arFields);
			if($actionConfiguration)
			{
				$arFields['SHORT_DESCRIPTION'] = serialize($actionConfiguration);
			}
		}

		$intUserID = 0;
		$boolUserExist = isset($USER) && $USER instanceof CUser;
		if ($boolUserExist)
			$intUserID = (int)$USER->GetID();
		$strDateFunction = $DB->GetNowFunction();
		$arFields['~TIMESTAMP_X'] = $strDateFunction;
		if ($boolUserExist)
		{
			if (!array_key_exists('MODIFIED_BY', $arFields) || (int)$arFields["MODIFIED_BY"] <= 0)
				$arFields["MODIFIED_BY"] = $intUserID;
		}
		if ($ACTION == 'ADD')
		{
			$arFields['~DATE_CREATE'] = $strDateFunction;
			if ($boolUserExist)
			{
				if (!array_key_exists('CREATED_BY', $arFields) || (int)$arFields["CREATED_BY"] <= 0)
					$arFields["CREATED_BY"] = $intUserID;
			}
		}

		return true;
	}

	/*
	* @deprecated deprecated since sale 14.11.0
	* @see \Bitrix\Sale\Internals\DiscountTable::delete
	*/
	public function Delete($ID)
	{
		$ID = (int)$ID;
		if ($ID <= 0)
			return false;

		$result = Sale\Internals\DiscountTable::delete($ID);
		return $result->isSuccess();
	}

	protected static function getDiscountResult(&$oldOrder, &$currentOrder, $extMode = false)
	{
		$extMode = ($extMode === true);
		$result = array();
		if (isset($oldOrder['PRICE_DELIVERY']) && isset($currentOrder['PRICE_DELIVERY']))
		{
			if ($oldOrder['PRICE_DELIVERY'] != $currentOrder['PRICE_DELIVERY'])
			{
				$absValue = $oldOrder['PRICE_DELIVERY'] - $currentOrder['PRICE_DELIVERY'];
				$fullValue = ($extMode && isset($currentOrder['PRICE_DELIVERY_ORIG']) ? $currentOrder['PRICE_DELIVERY_ORIG'] : $oldOrder['PRICE_DELIVERY']);
				$percValue = ($fullValue != 0 ? $absValue*100/$fullValue : 0);
				$result['DELIVERY'] = array(
					'TYPE' => 'D',
					'DISCOUNT_TYPE' => ($currentOrder['PRICE_DELIVERY'] < $oldOrder['PRICE_DELIVERY'] ? 'D' : 'M'),
					'VALUE' => $absValue,
					'VALUE_PERCENT' => $percValue,
					'DELIVERY_ID' => (isset($currentOrder['DELIVERY_ID']) ? $currentOrder['DELIVERY_ID'] : false)
				);
				unset($percValue, $fullValue, $absValue);
			}
		}
		if (!empty($oldOrder['BASKET_ITEMS']) && !empty($currentOrder['BASKET_ITEMS']))
		{
			foreach ($oldOrder['BASKET_ITEMS'] as $key => $item)
			{
				if (!isset($currentOrder['BASKET_ITEMS'][$key]))
					continue;
				if ($item['PRICE'] != $currentOrder['BASKET_ITEMS'][$key]['PRICE'])
				{
					$newItem = &$currentOrder['BASKET_ITEMS'][$key];
					$absValue = $item['PRICE'] - $newItem['PRICE'];
					$fullValue = ($extMode && isset($newItem['PRICE_ORIG']) ? $newItem['PRICE_ORIG'] : $item['PRICE']);
					$percValue = ($fullValue != 0 ? $absValue*100/$fullValue : 0);
					if (!isset($result['BASKET']))
						$result['BASKET'] = array();
					$result['BASKET'][] = array(
						'TYPE' => 'B',
						'DISCOUNT_TYPE' => ($newItem['PRICE'] < $item['PRICE'] ? 'D' : 'M'),
						'VALUE' => $absValue,
						'VALUE_PERCENT' => $percValue,
						'BASKET_NUM' => $key,
						'BASKET_ID' => (isset($newItem['ID']) ? $newItem['ID'] : '0'),
						'BASKET_PRODUCT_XML_ID' => (isset($newItem['PRODUCT_XML_ID']) && $newItem['PRODUCT_XML_ID'] != '' ? $newItem['PRODUCT_XML_ID'] : false),
						'PRODUCT_ID' => $newItem['PRODUCT_ID'],
						'MODULE' => $newItem['MODULE']
					);
					unset($percValue, $fullValue, $absValue, $newItem);
				}
			}
		}
		return $result;
	}

	protected static function changeDiscountResult(&$oldOrder, &$order, &$discountResult)
	{
		if (empty($discountResult['BASKET']) || count($discountResult['BASKET']) <= 1)
			return;
		$maxPrice = 0;
		$maxKey = -1;
		$basketKeys = array();
		foreach ($discountResult['BASKET'] as $key => $row)
		{
			$basketKeys[$key] = $row['BASKET_NUM'];
			if ($maxPrice < $oldOrder['BASKET_ITEMS'][$row['BASKET_NUM']]['PRICE'])
			{
				$maxPrice = $oldOrder['BASKET_ITEMS'][$row['BASKET_NUM']]['PRICE'];
				$maxKey = $key;
			}
		}
		unset($row, $key);
		unset($basketKeys[$maxKey]);
		foreach ($basketKeys as $key => $basketRow)
		{
			unset($discountResult['BASKET'][$key]);
			$order['BASKET_ITEMS'][$basketRow] = $oldOrder['BASKET_ITEMS'][$basketRow];
		}
		$discountResult['BASKET'] = array_values($discountResult['BASKET']);
	}

	protected function __Unpack($arOrder, $strUnpack)
	{
		$checkOrder = null;
		if (empty($strUnpack))
			return false;
		eval('$checkOrder='.$strUnpack.';');
		if (!is_callable($checkOrder))
			return false;
		$boolRes = $checkOrder($arOrder);
		unset($checkOrder);
		return $boolRes;
	}

	protected function __ApplyActions(&$arOrder, $strActions)
	{
		$applyOrder = null;
		if (!empty($strActions))
		{
			eval('$applyOrder='.$strActions.';');
			if (is_callable($applyOrder))
				$applyOrder($arOrder);
		}
	}

	protected function __ConvertOldFormat($strAction, &$arFields)
	{
		global $APPLICATION;

		$arMsg = array();
		$boolResult = true;

		$arNeedFields = array(
			'LID',
			'CURRENCY',
			'DISCOUNT_TYPE',
			'DISCOUNT_VALUE',
			'PRICE_FROM',
			'PRICE_TO',
		);
		$arUpdateFields = array(
			'DISCOUNT_VALUE',
			'PRICE_FROM',
			'PRICE_TO',
		);

		$strAction = ToUpper($strAction);
		if (!array_key_exists('CONDITIONS', $arFields) && !array_key_exists('ACTIONS', $arFields))
		{
			$strSiteCurrency = '';
			$boolUpdate = false;

			if ('UPDATE' == $strAction)
			{
				$boolNeedQuery = false;
				foreach ($arUpdateFields as &$strFieldID)
				{
					if (array_key_exists($strFieldID, $arFields))
					{
						$boolUpdate = true;
						break;
					}
				}
				if (isset($strFieldID))
					unset($strFieldID);
				if ($boolUpdate)
				{
					foreach ($arNeedFields as &$strFieldID)
					{
						if (!array_key_exists($strFieldID, $arFields))
						{
							$boolNeedQuery = true;
							break;
						}
					}
					if (isset($strFieldID))
						unset($strFieldID);

					if ($boolNeedQuery)
					{
						$rsDiscounts = CSaleDiscount::GetList(array(), array('ID' => $arFields['ID']), false, false, $arNeedFields);
						if ($arDiscount = $rsDiscounts->Fetch())
						{
							foreach ($arNeedFields as &$strFieldID)
							{
								if (!array_key_exists($strFieldID, $arFields))
								{
									$arFields[$strFieldID] = $arDiscount[$strFieldID];
								}
							}
							if (isset($strFieldID))
								unset($strFieldID);
						}
						else
						{
							$boolUpdate = false;
							$boolResult = false;
							$arMsg[] = array('id' => 'ID', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_ABSENT'));
						}
					}
				}
			}

			if ('ADD' == $strAction || $boolUpdate)
			{
				if (!array_key_exists('LID', $arFields))
				{
					$boolResult = false;
					$arMsg[] = array('id' => 'LID','text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_SITE_ID_ABSENT'));
				}
				else
				{
					$arFields['LID'] = strval($arFields['LID']);
					if ('' == $arFields['LID'])
					{
						$boolResult = false;
						$arMsg[] = array('id' => 'LID','text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_SITE_ID_ABSENT'));
					}
					else
					{
						$rsSites = CSite::GetByID($arFields["LID"]);
						if (!$arSite = $rsSites->Fetch())
						{
							$boolResult = false;
							$arMsg[] = array('id' => 'LID', 'text' => Loc::getMessage('SKGD_NO_SITE', array('#ID#' => $arFields['LID'])));
						}
						else
						{
							$strSiteCurrency = CSaleLang::GetLangCurrency($arFields['LID']);
						}
					}
				}

				if (!array_key_exists('CURRENCY', $arFields))
				{
					$boolResult = false;
					$arMsg[] = array('id' => 'CURRENCY', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_CURRENCY_ABSENT'));
				}
				else
				{
					$arFields['CURRENCY'] = strval($arFields['CURRENCY']);
					if ('' == $arFields['CURRENCY'])
					{
						$boolResult = false;
						$arMsg[] = array('id' => 'CURRENCY', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_CURRENCY_ABSENT'));
					}
					else
					{
						if (!($arCurrency = CCurrency::GetByID($arFields["CURRENCY"])))
						{
							$boolResult = false;
							$arMsg[] = array('id' => 'CURRENCY', 'text' => Loc::getMessage('SKGD_NO_CURRENCY', array('#ID#' => $arFields['CURRENCY'])));
						}
					}
				}

				if (!array_key_exists("DISCOUNT_TYPE", $arFields))
				{
					$boolResult = false;
					$arMsg[] = array('id' => 'DISCOUNT_TYPE', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_TYPE_ABSENT'));
				}
				else
				{
					$arFields["DISCOUNT_TYPE"] = strval($arFields["DISCOUNT_TYPE"]);
					if (CSaleDiscount::OLD_DSC_TYPE_PERCENT != $arFields["DISCOUNT_TYPE"] && CSaleDiscount::OLD_DSC_TYPE_FIX != $arFields["DISCOUNT_TYPE"])
					{
						$boolResult = false;
						$arMsg[] = array('id' => 'DISCOUNT_TYPE', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_TYPE_BAD'));
					}
				}

				if (!array_key_exists('DISCOUNT_VALUE', $arFields))
				{
					$boolResult = false;
					$arMsg[] = array('id' => 'DISCOUNT_VALUE', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_VALUE_ABSENT'));
				}
				else
				{
					$arFields['DISCOUNT_VALUE'] = (float)str_replace(',', '.', $arFields['DISCOUNT_VALUE']);
					if (0 >= $arFields['DISCOUNT_VALUE'])
					{
						$boolResult = false;
						$arMsg[] = array('id' => 'DISCOUNT_VALUE', 'text' => Loc::getMessage('BT_MOD_SALE_ERR_DSC_VALUE_BAD'));
					}
				}

				if ($boolResult)
				{
					$arConditions = array(
						'CLASS_ID' => 'CondGroup',
						'DATA' => array(
							'All' => 'AND',
							'True' => 'True',
						),
						'CHILDREN' => array(),
					);
					$arActions = array(
						'CLASS_ID' => 'CondGroup',
						'DATA' => array(
							'All' => 'AND',
							'True' => 'True',
						),
						'CHILDREN' => array(),
					);

					$boolCurrency = ($arFields['CURRENCY'] == $strSiteCurrency);

					if (array_key_exists('PRICE_FROM', $arFields))
					{
						$arFields["PRICE_FROM"] = str_replace(",", ".", strval($arFields["PRICE_FROM"]));
						$arFields["PRICE_FROM"] = doubleval($arFields["PRICE_FROM"]);
						if (0 < $arFields["PRICE_FROM"])
						{
							$dblValue = roundEx(($boolCurrency ? $arFields['PRICE_FROM'] : CCurrencyRates::ConvertCurrency($arFields['PRICE_FROM'], $arFields['CURRENCY'], $strSiteCurrency)), SALE_VALUE_PRECISION);
							$arConditions['CHILDREN'][] = array(
								'CLASS_ID' => 'CondBsktAmtGroup',
								'DATA' => array(
									'logic' => 'EqGr',
									'Value' => (string)$dblValue,
									'All' => 'AND',
								),
								'CHILDREN' => array(
								),
							);
							$arFields["PRICE_FROM"] = $dblValue;
						}
					}
					if (array_key_exists('PRICE_TO', $arFields))
					{
						$arFields["PRICE_TO"] = str_replace(",", ".", strval($arFields["PRICE_TO"]));
						$arFields["PRICE_TO"] = doubleval($arFields["PRICE_TO"]);
						if (0 < $arFields["PRICE_TO"])
						{
							$dblValue = roundEx(($boolCurrency ? $arFields['PRICE_TO'] : CCurrencyRates::ConvertCurrency($arFields['PRICE_TO'], $arFields['CURRENCY'], $strSiteCurrency)), SALE_VALUE_PRECISION);
							$arConditions['CHILDREN'][] = array(
								'CLASS_ID' => 'CondBsktAmtGroup',
								'DATA' => array(
									'logic' => 'EqLs',
									'Value' => (string)$dblValue,
									'All' => 'AND',
								),
								'CHILDREN' => array(
								),
							);
							$arFields["PRICE_TO"] = $dblValue;
						}
					}
					if (self::OLD_DSC_TYPE_PERCENT == $arFields['DISCOUNT_TYPE'])
					{
						$arActions['CHILDREN'][] = array(
							'CLASS_ID' => 'ActSaleBsktGrp',
							'DATA' => array(
								'Type' => 'Discount',
								'Value' => (string)roundEx($arFields['DISCOUNT_VALUE'], SALE_VALUE_PRECISION),
								'Unit' => 'Perc',
								'All' => 'AND',
							),
							'CHILDREN' => array(
							),
						);
					}
					else
					{
						$dblValue = roundEx(($boolCurrency ? $arFields['DISCOUNT_VALUE'] : CCurrencyRates::ConvertCurrency($arFields['DISCOUNT_VALUE'], $arFields['CURRENCY'], $strSiteCurrency)), SALE_VALUE_PRECISION);
						$arActions['CHILDREN'][] = array(
							'CLASS_ID' => 'ActSaleBsktGrp',
							'DATA' => array(
								'Type' => 'Discount',
								'Value' => (string)$dblValue,
								'Unit' => 'CurAll',
								'All' => 'AND',
							),
							'CHILDREN' => array(
							),
						);
						$arFields['DISCOUNT_VALUE'] = $dblValue;
					}

					$arFields['CONDITIONS'] = $arConditions;
					$arFields['ACTIONS'] = $arActions;
					$arFields['CURRENCY'] = $strSiteCurrency;
				}
				else
				{
					$obError = new CAdminException($arMsg);
					$APPLICATION->ThrowException($obError);
				}
			}
		}
		return $boolResult;
	}

	protected function __SetOldFields($strAction, &$arFields)
	{
		global $APPLICATION;

		$arMsg = array();
		$boolResult = true;

		$strAction = ToUpper($strAction);
		if (array_key_exists('CONDITIONS', $arFields) && !empty($arFields['CONDITIONS']))
		{
			$arConditions = false;
			if (!is_array($arFields['CONDITIONS']))
			{
				if (CheckSerializedData($arFields['CONDITIONS']))
				{
					$arConditions = unserialize($arFields['CONDITIONS']);
				}
			}
			else
			{
				$arConditions = $arFields['CONDITIONS'];
			}

			if (is_array($arConditions) && !empty($arConditions))
			{
				$obCond = new CSaleCondTree();
				$boolCond = $obCond->Init(BT_COND_MODE_SEARCH, BT_COND_BUILD_SALE, array());
				if ($boolCond)
				{
					$arResult = $obCond->GetConditionValues($arConditions);

				}
			}
		}
		if (array_key_exists('ACTIONS', $arFields) && !empty($arFields['ACTIONS']))
		{
			$arActions = false;
			if (!is_array($arFields['ACTIONS']))
			{
				if (CheckSerializedData($arFields['ACTIONS']))
				{
					$arActions = unserialize($arFields['ACTIONS']);
				}
			}
			else
			{
				$arActions = $arFields['ACTIONS'];
			}

			if (is_array($arActions) && !empty($arActions))
			{
				$obAct = new CSaleActionTree();
				$boolAct = $obAct->Init(BT_COND_MODE_SEARCH, BT_COND_BUILD_SALE_ACTIONS, array());
				if ($boolAct)
				{
					$arResult = $obAct->GetConditionValues($arActions);
				}
			}
		}

		if (!$boolResult)
		{
			$obError = new CAdminException($arMsg);
			$APPLICATION->ThrowException($obError);
		}

		return $boolResult;
	}

	protected function prepareDiscountConditions(&$conditions, &$result, &$handlers, $type, $site)
	{
		global $APPLICATION;

		$obCond = null;
		$result = '';
		$handlers = array();
		$type = (int)$type;
		if ($type != self::PREPARE_CONDITIONS && $type != self::PREPARE_ACTIONS || empty($conditions))
		{
			return false;
		}
		if (!is_array($conditions))
		{
			if (!CheckSerializedData($conditions))
			{
				if ($type == self::PREPARE_CONDITIONS)
				{
					$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_BAD_CONDITIONS"), "CONDITIONS");
				}
				else
				{
					$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_BAD_ACTIONS_EXT"), "ACTIONS");
				}
				return false;
			}
			$conditions = unserialize($conditions);
			if (!is_array($conditions) || empty($conditions))
			{
				if ($type == self::PREPARE_CONDITIONS)
				{
					$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_BAD_CONDITIONS"), "CONDITIONS");
				}
				else
				{
					$APPLICATION->ThrowException(Loc::getMessage("BT_MOD_SALE_DISC_ERR_BAD_ACTIONS_EXT"), "ACTIONS");
				}
				return false;
			}
		}

		if ($type == self::PREPARE_CONDITIONS)
		{
			$obCond = new CSaleCondTree();
			$boolCond = $obCond->Init(BT_COND_MODE_GENERATE, BT_COND_BUILD_SALE, array('INIT_CONTROLS' => array(
				'SITE_ID' => $site,
				'CURRENCY' => CSaleLang::GetLangCurrency($site),
			)));
		}
		else
		{
			$obCond = new CSaleActionTree();
			$boolCond = $obCond->Init(BT_COND_MODE_GENERATE, BT_COND_BUILD_SALE_ACTIONS, array());
		}
		if (!$boolCond)
		{
			return false;
		}
		$result = $obCond->Generate(
			$conditions,
			array(
				'ORDER' => '$arOrder',
				'ORDER_FIELDS' => '$arOrder',
				'ORDER_PROPS' => '$arOrder[\'PROPS\']',
				'ORDER_BASKET' => '$arOrder[\'BASKET_ITEMS\']',
				'BASKET' => '$arBasket',
				'BASKET_ROW' => '$row',
			)
		);
		if ($result == '')
		{
			if ($type == self::PREPARE_CONDITIONS)
			{
				$APPLICATION->ThrowException(Loc::getMessage('BT_MOD_SALE_DISC_ERR_BAD_CONDITIONS'), 'CONDITIONS');
			}
			else
			{
				$APPLICATION->ThrowException(Loc::getMessage('BT_MOD_SALE_DISC_ERR_BAD_ACTIONS_EXT'), 'ACTIONS');
			}
			return false;
		}
		else
		{
			$handlers['HANDLERS'] = $obCond->GetConditionHandlers();
			$handlers['ENTITY'] = $obCond->GetUsedEntityList();
			$handlers['EXECUTE_MODULE'] = $obCond->GetExecuteModule();
		}
		$conditions = serialize($conditions);

		return true;
	}

	protected function updateDiscountHandlers($discountID, $handlers, $update)
	{
		$discountID = (int)$discountID;
		if ($discountID <= 0 || empty($handlers) || !is_array($handlers))
			return;
		if (isset($handlers['MODULES']))
			Sale\Internals\DiscountModuleTable::updateByDiscount($discountID, $handlers['MODULES'], $update);
	}

	protected function getDiscountHandlers($discountList)
	{
		$result = array();
		if (!empty($discountList) && is_array($discountList))
		{
			$moduleList = Sale\Internals\DiscountModuleTable::getByDiscount($discountList);
			if (!empty($moduleList))
			{
				foreach ($moduleList as $discount => $discountModule)
				{
					$result[$discount] = array(
						'MODULES' => $discountModule,
						'EXT_FILES' => array()
					);
				}
				unset($discount, $discountModule, $moduleList);
			}
		}
		return $result;
	}

	/*
	* @deprecated deprecated since sale 14.11.0
	* @see \Bitrix\Sale\Internals\DiscountGroupTable::updateByDiscount
	*/
	protected function updateUserGroups($discountID, $userGroups, $active = '', $updateData)
	{
		Sale\Internals\DiscountGroupTable::updateByDiscount($discountID, $userGroups, $active, $updateData);
	}
}