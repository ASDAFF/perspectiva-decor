<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\PriceMaths;

class CBitrixBasketComponent extends CBitrixComponent
{
	public $arCustomSelectFields = array();
	public $arIblockProps = array();
	public $weightKoef = 0;
	public $weightUnit = 0;
	public $quantityFloat = "N";
	public $countDiscount4AllQuantity = "N";
	public $priceVatShowValue = "N";
	public $hideCoupon = "N";
	public $usePrepayment = "N";
	public $pathToOrder = "/personal/order.php";
	public $columns = array();
	public $offersProps = array();
	protected static $iblockIncluded = null;
	protected static $catalogIncluded = null;
	protected static $highLoadInclude = null;

	public function onPrepareComponentParams($arParams)
	{
		$arParams["PATH_TO_ORDER"] = trim($arParams["PATH_TO_ORDER"]);
		if (strlen($arParams["PATH_TO_ORDER"]) <= 0)
			$arParams["PATH_TO_ORDER"] = "order.php";

		if (!isset($arParams['QUANTITY_FLOAT']))
			$arParams['QUANTITY_FLOAT'] = 'N';

		$arParams["HIDE_COUPON"] = ($arParams["HIDE_COUPON"] == "Y") ? "Y" : "N";
		$arParams["COUNT_DISCOUNT_4_ALL_QUANTITY"] = ($arParams["COUNT_DISCOUNT_4_ALL_QUANTITY"] == "Y") ? "Y" : "N";
		$arParams['PRICE_VAT_SHOW_VALUE'] = ($arParams['PRICE_VAT_SHOW_VALUE'] == 'N') ? 'N' : 'Y';
		$arParams["USE_PREPAYMENT"] = ($arParams["USE_PREPAYMENT"] == 'Y') ? 'Y' : 'N';
		$arParams["AUTO_CALCULATION"] = ($arParams["AUTO_CALCULATION"] == 'N') ? 'N' : 'Y';

		$arParams["WEIGHT_KOEF"] = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_koef', 1, SITE_ID));
		$arParams["WEIGHT_UNIT"] = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_unit', "", SITE_ID));

		// default columns
		if (empty($arParams["COLUMNS_LIST"]) || !is_array($arParams["COLUMNS_LIST"]))
			$arParams["COLUMNS_LIST"] = array("NAME", "DISCOUNT", "WEIGHT", "DELETE", "DELAY", "TYPE", "PRICE", "QUANTITY", "SUM");

		// required columns
		if (!in_array("NAME", $arParams["COLUMNS_LIST"]))
			$arParams["COLUMNS_LIST"] = array_merge(array("NAME"), $arParams["COLUMNS_LIST"]);

		if (!in_array("QUANTITY", $arParams["COLUMNS_LIST"]))
			$arParams["COLUMNS_LIST"][] = "QUANTITY";

		if (!in_array("PRICE", $arParams["COLUMNS_LIST"]))
		{
			if (!in_array("SUM", $arParams["COLUMNS_LIST"]))
			{
				$arParams["COLUMNS_LIST"][] = "PRICE";
			}
			else // make PRICE before SUM
			{
				$index = array_search("SUM", $arParams["COLUMNS_LIST"]);
				array_splice($arParams["COLUMNS_LIST"], $index, 0, "PRICE");
			}
		}

		if (!isset($arParams["OFFERS_PROPS"]) && !is_array($arParams["OFFERS_PROPS"]))
			$arParams["OFFERS_PROPS"] = array();

		if (!isset($arParams["ACTION_VARIABLE"])
			|| strlen(trim($arParams["ACTION_VARIABLE"])) <= 0
			|| !preg_match('/[a-zA-Z0-9_-~.!*\'(),]/', trim($arParams["ACTION_VARIABLE"]))
			)
			$arParams["ACTION_VARIABLE"] = "basketAction";
		else
			$arParams["ACTION_VARIABLE"] = trim($arParams["ACTION_VARIABLE"]);

		//default gifts
		if(empty($arParams['USE_GIFTS']))
		{
			$arParams['USE_GIFTS'] = 'Y';
		}
		if(empty($arParams['GIFTS_PLACE']))
		{
			$arParams['GIFTS_PLACE'] = 'BOTTOM';
		}
		if(!isset($arParams['GIFTS_PAGE_ELEMENT_COUNT']))
		{
			$arParams['GIFTS_PAGE_ELEMENT_COUNT'] = 4;
		}

		return $arParams;
	}

	public function onIncludeComponentLang()
	{
		Loc::loadMessages(__FILE__);
	}

	public function executeComponent()
	{
		if (!Loader::includeModule('sale'))
			return false;
		DiscountCouponsManager::init();
		$this->setFramemode(false);
		$this->weightKoef = $this->arParams["WEIGHT_KOEF"];
		$this->weightUnit = $this->arParams["WEIGHT_UNIT"];
		$this->columns = $this->arParams["COLUMNS_LIST"];
		$this->offersProps = $this->arParams["OFFERS_PROPS"];

		$this->quantityFloat = $this->arParams["QUANTITY_FLOAT"];

		$this->countDiscount4AllQuantity = $this->arParams["COUNT_DISCOUNT_4_ALL_QUANTITY"];
		$this->priceVatShowValue = $this->arParams["PRICE_VAT_SHOW_VALUE"];
		$this->hideCoupon = $this->arParams["HIDE_COUPON"];
		$this->usePrepayment = $this->arParams["USE_PREPAYMENT"];
		$this->pathToOrder = $this->arParams["PATH_TO_ORDER"];

		return parent::executeComponent();
	}

	public function getCustomColumns()
	{
		$propertyCount = 0;
		define("PROPERTY_COUNT_LIMIT", 24); // too much properties cause sql join error

		$res = array();
		foreach ($this->columns as $key => $value) // making grid headers array
		{
			$name = '';
			if (strpos($value, "PROPERTY_") !== false)
			{
				$propertyCount++;
				if ($propertyCount > PROPERTY_COUNT_LIMIT)
					continue;

				$value = ToUpper($value);

				$this->arCustomSelectFields[] = $value; // array of iblock properties to select
				$id = $value."_VALUE";

				if (self::$iblockIncluded === null)
					self::$iblockIncluded = Loader::includeModule('iblock');
				if (self::$iblockIncluded)
				{
					$dbres = CIBlockProperty::GetList(array(), array("CODE" => substr($value, 9)));
					if ($arres = $dbres->GetNext())
					{
						$name = $arres["NAME"];
						$this->arIblockProps[substr($value, 9)] = $arres;
					}
				}
			}
			else
			{
				$id = $value;
			}

			$arColumn = array(
				"id" => $id,
				"name" => $name
			);

			$res[] = $arColumn;
		}

		return $res;
	}

	public function getBasketItems()
	{
		global $APPLICATION;
		if (self::$catalogIncluded === null)
			self::$catalogIncluded = Loader::includeModule('catalog');
		self::$iblockIncluded = self::$catalogIncluded;

		$fuserId = CSaleBasket::GetBasketUserID();
		$sessionBasketQuantity = \Bitrix\Sale\BasketComponentHelper::getFUserBasketQuantity($fuserId, SITE_ID);
		$sessionBasketPrice = \Bitrix\Sale\BasketComponentHelper::getFUserBasketPrice($fuserId, SITE_ID);


		$options = array(
			'CORRECT_RATIO' => array_key_exists('CORRECT_RATIO', $this->arParams) ? $this->arParams['CORRECT_RATIO'] : 'N'
		);

		$arResult["WARNING_MESSAGE"] = array();
		$arResult["ERROR_MESSAGE"] = '';

		$r = CSaleBasket::refreshFUserBasket(CSaleBasket::GetBasketUserID(), SITE_ID, $options);
		if (!$r->isSuccess())
		{
			$arResult["WARNING_MESSAGE"] = $r->getErrorMessages();
		}

		$bShowReady = false;
		$bShowDelay = false;
		$bShowSubscribe = false;
		$bShowNotAvail = false;
		$allSum = 0;
		$allWeight = 0;
		$allCurrency = CSaleLang::GetLangCurrency(SITE_ID);
		$allVATSum = 0;
		$arParents = array();

		$arResult["ITEMS"]["AnDelCanBuy"] = array();
		$arResult["ITEMS"]["DelDelCanBuy"] = array();
		$arResult["ITEMS"]["nAnCanBuy"] = array();
		$arResult["ITEMS"]["ProdSubscribe"] = array();
		$DISCOUNT_PRICE_ALL = 0;
		$arResult["EVENT_ONCHANGE_ON_START"] = "N";

		// BASKET PRODUCTS (including measures, ratio, iblock properties data)

		$arImgFields = array("PREVIEW_PICTURE", "DETAIL_PICTURE");
		$arBasketItems = array();
		$basketKey = 0;
		$basketIds = array();
		$arSku2Parent = array();
		$arSetParentWeight = array();
		$arElementId = array();
		

		$dbItems = CSaleBasket::GetList(
			array("ID" => "ASC"),
			array(
				"FUSER_ID" => $fuserId,
				"LID" => SITE_ID,
				"ORDER_ID" => "NULL"
			),
			false,
			false,
			array(
				"ID", "NAME", "CALLBACK_FUNC", "MODULE", "PRODUCT_ID", "PRODUCT_PRICE_ID", "QUANTITY", "DELAY", "CAN_BUY",
				"PRICE", "WEIGHT", "DETAIL_PAGE_URL", "NOTES", "CURRENCY", "VAT_RATE", "CATALOG_XML_ID",
				"PRODUCT_XML_ID", "SUBSCRIBE", "DISCOUNT_PRICE", "PRODUCT_PROVIDER_CLASS", "TYPE", "SET_PARENT_ID", 'PRODUCT_PRICE_ID'
			)
		);
		while ($arItem = $dbItems->GetNext())
		{
			$arItem['PROPS'] = array();
			$arBasketItems[$basketKey] = $arItem;
			$basketIds[$arItem['ID']] = &$arBasketItems[$basketKey];
			$basketKey++;

			if (CSaleBasketHelper::isSetItem($arItem))
				continue;

			$arElementId[] = $arItem["PRODUCT_ID"];
		}

		if (!empty($arElementId) && self::$catalogIncluded)
		{
			$productList = CCatalogSKU::getProductList($arElementId);
			if (!empty($productList))
			{
				foreach ($productList as $offerId => $offerInfo)
				{
					$offerInfo['PRODUCT_ID'] = $offerInfo['ID'];
					$arElementId[] = $offerInfo['ID'];
					$arSku2Parent[$offerId] = $offerInfo['ID'];
					$arParents[$offerId] = $offerInfo;
				}
				unset($offerInfo, $offerId);
			}
			unset($productList);

			// get measures, ratio, sku props data and available quantity
			$arBasketItems = getMeasures($arBasketItems);
			$arBasketItems = getRatio($arBasketItems);
			$arBasketItems = $this->getAvailableQuantity($arBasketItems);

			$propsIterator = CSaleBasket::GetPropsList(
				array('BASKET_ID' => 'ASC', 'SORT' => 'ASC', 'ID' => 'ASC'),
				array('BASKET_ID' => array_keys($basketIds))
			);
			while ($property = $propsIterator->GetNext())
			{
				$property['CODE'] = (string)$property['CODE'];
				if ($property['CODE'] == 'CATALOG.XML_ID' || $property['CODE'] == 'PRODUCT.XML_ID' || $property['CODE'] == 'SUM_OF_CHARGE')
					continue;
				if (!isset($basketIds[$property['BASKET_ID']]))
					continue;
				$basketIds[$property['BASKET_ID']]['PROPS'][] = $property;
			}
			unset($property, $propsIterator, $basketIds);
		}

		// get product properties data
		$arProductData = getProductProps($arElementId, array_merge(array("ID"), $arImgFields, $this->arCustomSelectFields));

		foreach ($arBasketItems as &$arItem)
		{
			$quantityIsFloat = false;
			if (number_format(doubleval($arItem['QUANTITY']), 2, '.', '') != intval($arItem['QUANTITY']))
			{
				$quantityIsFloat = true;
			}

			$arItem["QUANTITY"] = ($quantityIsFloat === false && $this->quantityFloat != "Y") ? intval($arItem['QUANTITY']) : (number_format(doubleval($arItem['QUANTITY']), 4, '.', '') * 1);

			$arItem["PRICE_VAT_VALUE"] = (($arItem["PRICE"] * $arItem["QUANTITY"] / ($arItem["VAT_RATE"] +1)) * $arItem["VAT_RATE"]) / $arItem["QUANTITY"];
			//$arItem["PRICE_VAT_VALUE"] = (($arItem["PRICE"] / ($arItem["VAT_RATE"] +1)) * $arItem["VAT_RATE"]);
			$arItem["PRICE_FORMATED"] = CCurrencyLang::CurrencyFormat($arItem["PRICE"], $arItem["CURRENCY"], true);

			$arItem["WEIGHT"] = doubleval($arItem["WEIGHT"]);
			$arItem["WEIGHT_FORMATED"] = roundEx(doubleval($arItem["WEIGHT"] / $this->weightKoef), SALE_WEIGHT_PRECISION)." ".$this->weightUnit;

			if (CSaleBasketHelper::isSetItem($arItem))
				$arSetParentWeight[$arItem["SET_PARENT_ID"]] += $arItem["WEIGHT"] * $arItem["QUANTITY"];

			if (isset($arProductData[$arItem['PRODUCT_ID']]) && is_array($arProductData[$arItem['PRODUCT_ID']]))
			{
				foreach ($arProductData[$arItem["PRODUCT_ID"]] as $key => $value)
				{
					if (strpos($key, "PROPERTY_") !== false || in_array($key, $arImgFields))
						$arItem[$key] = $value;
				}
			}

			if (array_key_exists($arItem["PRODUCT_ID"], $arSku2Parent)) // if sku element doesn't have value of some property - we'll show parent element value instead
			{
				$replaceImageFields = true;
				$arFieldsToFill = array_merge($this->arCustomSelectFields, $arImgFields); // fields to be filled with parents' values if empty

				$parentId = $arSku2Parent[$arItem["PRODUCT_ID"]];

				foreach($arImgFields as $imageFieldName)
				{
					if (!empty($arProductData[$arItem["PRODUCT_ID"]][$imageFieldName]))
					{
						$replaceImageFields = false;
						$arItem[$imageFieldName] = $arProductData[$arItem["PRODUCT_ID"]][$imageFieldName];
					}
				}

				foreach ($arFieldsToFill as $field)
				{
					$fieldVal = (in_array($field, $arImgFields)) ? $field : $field."_VALUE";

					if ((!isset($arItem[$fieldVal]) || (isset($arItem[$fieldVal]) && strlen($arItem[$fieldVal]) == 0))
						&& (isset($arProductData[$parentId][$fieldVal]) && !empty($arProductData[$parentId][$fieldVal]))) // can be array or string
					{
						if (in_array($field, $arImgFields) && !$replaceImageFields)
							continue;

						$arItem[$fieldVal] = $arProductData[$parentId][$fieldVal];
					}
				}
			}

			foreach ($arItem as $key => $value) // format properties' values
			{
				if ((strpos($key, "PROPERTY_", 0) === 0) && (strrpos($key, "_VALUE") == strlen($key) - 6))
				{
					$code = ToUpper(str_replace(array("PROPERTY_", "_VALUE"), "", $key));
					$propData = $this->arIblockProps[$code];
					$arItem[$key] = CSaleHelper::getIblockPropInfo($value, $propData);
				}
			}

			$arItem["PREVIEW_PICTURE_SRC"] = "";
			if (isset($arItem["PREVIEW_PICTURE"]) && intval($arItem["PREVIEW_PICTURE"]) > 0)
			{
				$arImage = CFile::GetFileArray($arItem["PREVIEW_PICTURE"]);
				if ($arImage)
				{
					$arFileTmp = CFile::ResizeImageGet(
						$arImage,
						array("width" => "110", "height" =>"110"),
						BX_RESIZE_IMAGE_PROPORTIONAL,
						true
					);

					$arItem["PREVIEW_PICTURE_SRC"] = $arFileTmp["src"];
				}
			}

			$arItem["DETAIL_PICTURE_SRC"] = "";
			if (isset($arItem["DETAIL_PICTURE"]) && intval($arItem["DETAIL_PICTURE"]) > 0)
			{
				$arImage = CFile::GetFileArray($arItem["DETAIL_PICTURE"]);
				if ($arImage)
				{
					$arFileTmp = CFile::ResizeImageGet(
						$arImage,
						array("width" => "110", "height" =>"110"),
						BX_RESIZE_IMAGE_PROPORTIONAL,
						true
					);

					$arItem["DETAIL_PICTURE_SRC"] = $arFileTmp["src"];
				}
			}
		}
		unset($arItem);

		// get sku props data
		if (!empty($arBasketItems) && self::$catalogIncluded && isset($this->offersProps) && !empty($this->offersProps))
		{
			$arBasketItems = $this->getSkuPropsData($arBasketItems, $arParents, $this->offersProps);
		}

		// count weight for set parent products
		foreach ($arBasketItems as &$arItem)
		{
			if (CSaleBasketHelper::isSetParent($arItem))
			{
				$arItem["WEIGHT"] = $arSetParentWeight[$arItem["ID"]] / $arItem["QUANTITY"];
				$arItem["WEIGHT_FORMATED"] = roundEx(doubleval($arItem["WEIGHT"] / $this->weightKoef), SALE_WEIGHT_PRECISION)." ".$this->weightUnit;
			}
		}

		if (isset($arItem))
			unset($arItem);

		// fill item arrays for old templates
		foreach ($arBasketItems as &$arItem)
		{
			if (CSaleBasketHelper::isSetItem($arItem))
				continue;

			$arItem['DISCOUNT_PRICE'] = (float)$arItem['DISCOUNT_PRICE'];
			$arItem['PRICE'] = (float)$arItem['PRICE'];
			$arItem['DISCOUNT_PRICE_PERCENT'] = 0;
			$arItem['DISCOUNT_PRICE_PERCENT_FORMATED'] = '';
			$arItem['FULL_PRICE'] = $arItem['PRICE'];
			if ($arItem['DISCOUNT_PRICE'] > 0)
			{
				if (($arItem['DISCOUNT_PRICE'] + $arItem['PRICE']) > 0)
				{
					$arItem['DISCOUNT_PRICE_PERCENT'] = ($arItem['DISCOUNT_PRICE']*100)/($arItem['DISCOUNT_PRICE'] + $arItem['PRICE']);
					$arItem['DISCOUNT_PRICE_PERCENT_FORMATED'] = CSaleBasketHelper::formatQuantity($arItem['DISCOUNT_PRICE_PERCENT']).'%';
					$arItem['FULL_PRICE'] = $arItem["PRICE"] + $arItem["DISCOUNT_PRICE"];
				}
			}
			$arItem['FULL_PRICE_FORMATED'] = CCurrencyLang::CurrencyFormat($arItem['FULL_PRICE'], $arItem['CURRENCY'], true);

			if ($arItem["CAN_BUY"] == "Y" && $arItem["DELAY"] == "N")
			{
				$allSum += ($arItem["PRICE"] * $arItem["QUANTITY"]);
				$allWeight += ($arItem["WEIGHT"] * $arItem["QUANTITY"]);
				$allVATSum += roundEx($arItem["PRICE_VAT_VALUE"] * $arItem["QUANTITY"], SALE_VALUE_PRECISION);

				$bShowReady = true;
				if ($arItem["DISCOUNT_PRICE"] > 0)
					$DISCOUNT_PRICE_ALL += $arItem["DISCOUNT_PRICE"] * $arItem["QUANTITY"];

				$arResult["ITEMS"]["AnDelCanBuy"][] = $arItem;
			}
			elseif ($arItem["CAN_BUY"] == "Y" && $arItem["DELAY"] == "Y")
			{
				$bShowDelay = true;
				$arItem["SUM"] = CCurrencyLang::CurrencyFormat($arItem["PRICE"] * $arItem["QUANTITY"], $arItem["CURRENCY"], true);
				$arResult["ITEMS"]["DelDelCanBuy"][] = $arItem;
			}
			elseif ($arItem["CAN_BUY"] == "N" && $arItem["SUBSCRIBE"] == "Y")
			{
				$bShowSubscribe = true;

				$arResult["ITEMS"]["ProdSubscribe"][] = $arItem;
			}
			else
			{
				$bShowNotAvail = true;
				$arItem["NOT_AVAILABLE"] = true;

				$arResult["ITEMS"]["nAnCanBuy"][] = $arItem;
			}
		}
		unset($arItem);

		$arResult["ShowReady"] = ($bShowReady ? "Y" : "N" );
		$arResult["ShowDelay"] = ($bShowDelay ? "Y" : "N" );
		$arResult["ShowNotAvail"] = ($bShowNotAvail ? "Y" : "N" );
		$arResult["ShowSubscribe"] = ($bShowSubscribe ? "Y" : "N" );

		$arOrder = array(
			'SITE_ID' => SITE_ID,
			'USER_ID' => $GLOBALS["USER"]->GetID(),
			'ORDER_PRICE' => $allSum,
			'ORDER_WEIGHT' => $allWeight,
			'BASKET_ITEMS' => $arResult["ITEMS"]["AnDelCanBuy"]
		);

		$arOptions = array(
			'COUNT_DISCOUNT_4_ALL_QUANTITY' => $this->countDiscount4AllQuantity,
		);

		$arErrors = array();

		CSaleDiscount::DoProcessOrder($arOrder, $arOptions, $arErrors);

		if (isset($arOrder['ORDER_PRICE']))
		{
			$roundOrderFields = CSaleOrder::getRoundFields();
			foreach ($arOrder as $fieldName => $fieldValue)
			{
				if (in_array($fieldName, $roundOrderFields))
				{
					$arOrder[$fieldName] = PriceMaths::roundPrecision($arOrder[ $fieldName ]);
				}
			}
		}

		if (!empty($arOrder['BASKET_ITEMS']) && is_array($arOrder['BASKET_ITEMS']))
		{
			$arOrder['ORDER_PRICE'] = 0;
			$roundBasketFields = CSaleBasket::getRoundFields();
			foreach ($arOrder['BASKET_ITEMS'] as &$basketItem)
			{
				foreach($basketItem as $fieldName => $fieldValue)
				{
					if (in_array($fieldName, $roundBasketFields))
					{
						if (isset($basketItem[$fieldName]))
						{
							$basketItem[$fieldName] = PriceMaths::roundPrecision($basketItem[ $fieldName ]);
						}
					}
				}

				$arOrder['ORDER_PRICE'] += $basketItem['PRICE'] * $basketItem['QUANTITY'];
			}

			$arOrder['ORDER_PRICE'] = PriceMaths::roundPrecision($arOrder['ORDER_PRICE']);
		}

		$allSum = 0;
		$allWeight = 0;
		$allVATSum = 0;

		$DISCOUNT_PRICE_ALL = 0;
		$priceWithoutDiscount = 0;

		foreach ($arOrder["BASKET_ITEMS"] as &$arOneItem)
		{
			$allWeight += ($arOneItem["WEIGHT"] * $arOneItem["QUANTITY"]);
			$allSum += ($arOneItem["PRICE"] * $arOneItem["QUANTITY"]);

			if (array_key_exists('VAT_VALUE', $arOneItem))
				$arOneItem["PRICE_VAT_VALUE"] = $arOneItem["VAT_VALUE"];
			$allVATSum += roundEx($arOneItem["PRICE_VAT_VALUE"] * $arOneItem["QUANTITY"], SALE_VALUE_PRECISION);
			$arOneItem["PRICE_FORMATED"] = CCurrencyLang::CurrencyFormat($arOneItem["PRICE"], $arOneItem["CURRENCY"], true);

			$arOneItem["FULL_PRICE"] = PriceMaths::roundByFormatCurrency($arOneItem["PRICE"] + $arOneItem["DISCOUNT_PRICE"], $arOneItem["CURRENCY"]);
			$arOneItem["FULL_PRICE_FORMATED"] = CCurrencyLang::CurrencyFormat($arOneItem["FULL_PRICE"], $arOneItem["CURRENCY"], true);

			$arOneItem["SUM"] = CCurrencyLang::CurrencyFormat($arOneItem["PRICE"] * $arOneItem["QUANTITY"], $arOneItem["CURRENCY"], true);

			if (0 < doubleval($arOneItem["DISCOUNT_PRICE"] + $arOneItem["PRICE"]))
			{
				$arOneItem["DISCOUNT_PRICE_PERCENT"] = PriceMaths::roundByFormatCurrency($arOneItem["DISCOUNT_PRICE"] * 100 / ($arOneItem["DISCOUNT_PRICE"] + $arOneItem["PRICE"]), $arOneItem["CURRENCY"]);
			}
			else
			{
				$arOneItem["DISCOUNT_PRICE_PERCENT"] = 0;
			}
			$arOneItem["DISCOUNT_PRICE_PERCENT_FORMATED"] = CSaleBasketHelper::formatQuantity($arOneItem["DISCOUNT_PRICE_PERCENT"])."%";
			$DISCOUNT_PRICE_ALL += $arOneItem["DISCOUNT_PRICE"] * $arOneItem["QUANTITY"];
		}
		unset($arOneItem);

		$arResult["ITEMS"]["AnDelCanBuy"] = $arOrder["BASKET_ITEMS"];

		// fill grid data (for new templates with custom columns)
		foreach ($arResult["ITEMS"] as $type => $arItems)
		{
			foreach ($arItems as $k => $arItem)
			{
				$arResult["GRID"]["ROWS"][$arItem["ID"]] = $arItem;
			}
		}

		if (($sessionBasketPrice != $allSum) || (count($arOrder["BASKET_ITEMS"]) != $sessionBasketQuantity))
		{
			\Bitrix\Sale\BasketComponentHelper::updateFUserBasket($fuserId, SITE_ID);
			$arResult["EVENT_ONCHANGE_ON_START"] = "Y";
		}

		$arResult["allSum"] = PriceMaths::roundByFormatCurrency($allSum, $allCurrency);
		$arResult["allWeight"] = $allWeight;
		$arResult["allWeight_FORMATED"] = roundEx(doubleval($allWeight/$this->weightKoef), SALE_WEIGHT_PRECISION)." ".$this->weightUnit;
		$arResult["allSum_FORMATED"] = CCurrencyLang::CurrencyFormat($allSum, $allCurrency, true);
		$arResult["DISCOUNT_PRICE_FORMATED"] = CCurrencyLang::CurrencyFormat($arResult["DISCOUNT_PRICE"], $allCurrency, true);
		$arResult["PRICE_WITHOUT_DISCOUNT"] = CCurrencyLang::CurrencyFormat($allSum + $DISCOUNT_PRICE_ALL, $allCurrency, true);

		if ($this->priceVatShowValue == 'Y')
		{
			$arResult["allVATSum"] = PriceMaths::roundByFormatCurrency($allVATSum, $allCurrency);
			$arResult["allVATSum_FORMATED"] = CCurrencyLang::CurrencyFormat($allVATSum, $allCurrency, true);
			$arResult["allSum_wVAT_FORMATED"] = CCurrencyLang::CurrencyFormat(doubleval($arResult["allSum"]-$allVATSum), $allCurrency, true);
		}

		$arResult['COUPON_LIST'] = array();
		$arResult['COUPON'] = '';
		if ($this->hideCoupon != "Y")
		{
			$arCoupons = DiscountCouponsManager::get(true, array(), true, true);
			if (!empty($arCoupons))
			{
				foreach ($arCoupons as &$oneCoupon)
				{
					if ($arResult['COUPON'] == '')
						$arResult['COUPON'] = $oneCoupon['COUPON'];
					if ($oneCoupon['STATUS'] == DiscountCouponsManager::STATUS_NOT_FOUND || $oneCoupon['STATUS'] == DiscountCouponsManager::STATUS_FREEZE)
						$oneCoupon['JS_STATUS'] = 'BAD';
					elseif ($oneCoupon['STATUS'] == DiscountCouponsManager::STATUS_NOT_APPLYED || $oneCoupon['STATUS'] == DiscountCouponsManager::STATUS_ENTERED)
						$oneCoupon['JS_STATUS'] = 'ENTERED';
					else
						$oneCoupon['JS_STATUS'] = 'APPLYED';
					$oneCoupon['JS_CHECK_CODE'] = '';
					if (isset($oneCoupon['CHECK_CODE_TEXT']))
					{
						$oneCoupon['JS_CHECK_CODE'] = (is_array($oneCoupon['CHECK_CODE_TEXT']) ? implode('<br>', $oneCoupon['CHECK_CODE_TEXT']) : $oneCoupon['CHECK_CODE_TEXT']);
					}
					$arResult['COUPON_LIST'][] = $oneCoupon;
				}
				unset($oneCoupon);
				$arResult['COUPON_LIST'] = array_values($arCoupons);
			}
			unset($arCoupons);
		}
		if (empty($arBasketItems))
		{
			$arResult["ERROR_MESSAGE"] .= (strval(trim($arResult["ERROR_MESSAGE"])) != ''? "\n" : "") . Loc::getMessage("SALE_EMPTY_BASKET");
		}

		$arResult["DISCOUNT_PRICE_ALL"] = $DISCOUNT_PRICE_ALL;
		$arResult["APPLIED_DISCOUNT_LIST"] = $arOrder['DISCOUNT_LIST'];
		$arResult["FULL_DISCOUNT_LIST"] = $arOrder['FULL_DISCOUNT_LIST'];
		$arResult["DISCOUNT_PRICE_ALL_FORMATED"] = CCurrencyLang::CurrencyFormat($DISCOUNT_PRICE_ALL, $allCurrency, true);

		if($this->usePrepayment == "Y")
		{
			if(doubleval($arResult["allSum"]) > 0)
			{
				$personType = array();
				$dbPersonType = CSalePersonType::GetList(array("SORT" => "ASC", "NAME" => "ASC"), array("LID" => SITE_ID, "ACTIVE" => "Y"));
				while($arPersonType = $dbPersonType->Fetch())
				{
					$personType[] = $arPersonType["ID"];
				}

				if(!empty($personType))
				{
					$dbPaySysAction = CSalePaySystemAction::GetList(
							array(),
							array(
									"PS_ACTIVE" => "Y",
									"HAVE_PREPAY" => "Y",
									"PERSON_TYPE_ID" => $personType,
								),
							false,
							false,
							array("ID", "PAY_SYSTEM_ID", "PERSON_TYPE_ID", "NAME", "ACTION_FILE", "RESULT_FILE", "NEW_WINDOW", "PARAMS", "ENCODING", "LOGOTIP")
						);
					if ($arPaySysAction = $dbPaySysAction->Fetch())
					{
						CSalePaySystemAction::InitParamarrays(false, false, $arPaySysAction["PARAMS"]);

						$pathToAction = $_SERVER["DOCUMENT_ROOT"].$arPaySysAction["ACTION_FILE"];

						$pathToAction = str_replace("\\", "/", $pathToAction);
						while (substr($pathToAction, strlen($pathToAction) - 1, 1) == "/")
							$pathToAction = substr($pathToAction, 0, strlen($pathToAction) - 1);

						if (file_exists($pathToAction))
						{
							if (is_dir($pathToAction) && file_exists($pathToAction."/pre_payment.php"))
								$pathToAction .= "/pre_payment.php";

							try
							{
								include_once($pathToAction);
							}
							catch(\Bitrix\Main\SystemException $e)
							{
								if($e->getCode() == CSalePaySystemAction::GET_PARAM_VALUE)
									$message = Loc::getMessage("SOA_TEMPL_ORDER_PS_ERROR");
								else
									$message = $e->getMessage();

								$arResult["ERROR_MESSAGE"] .= (strval(trim($arResult["ERROR_MESSAGE"])) != ''? "\n" : "") . $message;
							}

							$psPreAction = new CSalePaySystemPrePayment;

							if($psPreAction->init())
							{
								$orderData = array(
										"PATH_TO_ORDER" => $this->pathToOrder,
										"AMOUNT" => $arResult["allSum"],
										"BASKET_ITEMS" => $arResult["ITEMS"]["AnDelCanBuy"],
									);
								if(!$psPreAction->BasketButtonAction($orderData))
								{
									if($e = $APPLICATION->GetException())
										$arResult["WARNING_MESSAGE"][] = $e->GetString();
								}

								$arResult["PREPAY_BUTTON"] = $psPreAction->BasketButtonShow();
							}
						}
					}
				}
			}
		}

		if (empty($arBasketItems) && !empty($arResult['WARNING_MESSAGE']))
		{
			$arResult["ERROR_MESSAGE"] .= (strval(trim($arResult["ERROR_MESSAGE"])) != ''? "\n" : "") . join('\n', $arResult['WARNING_MESSAGE']);
		}

		return $arResult;
	}

	public function getSkuPropsData($arBasketItems, $arParents, $arSkuProps = array())
	{
		$arRes = array();
		$arSkuIblockID = array();

		if (is_array($arParents))
		{
			$updateBasketProps = array();

			foreach ($arBasketItems as &$arItem)
			{
				if (!isset($arItem['MODULE']) || $arItem['MODULE'] != 'catalog')
					continue;
				if (!isset($arParents[$arItem['PRODUCT_ID']]))
					continue;

				$arSKU = CCatalogSKU::GetInfoByProductIBlock($arParents[$arItem['PRODUCT_ID']]['IBLOCK_ID']);
				if (empty($arSKU))
					continue;

				if (!isset($arSkuIblockID[$arSKU['IBLOCK_ID']]))
					$arSkuIblockID[$arSKU['IBLOCK_ID']] = $arSKU;

				$arItem['IBLOCK_ID'] = $arSKU['IBLOCK_ID'];
				$arItem['SKU_PROPERTY_ID'] = $arSKU['SKU_PROPERTY_ID'];

				$needSkuProps = static::getMissingPropertyCodes($arItem['PROPS'], $arSkuProps);
				if (!empty($needSkuProps))
				{
					if (!isset($updateBasketProps[$arItem['PRODUCT_ID']]))
						$updateBasketProps[$arItem['PRODUCT_ID']] = array();
					$updateBasketProps[$arItem['PRODUCT_ID']][$arItem['ID']] = $needSkuProps;
				}
				unset($needSkuProps);
			}
			unset($arItem);

			foreach ($arSkuIblockID as $skuIblockID => $arSKU)
			{
				// possible props values
				$rsProps = CIBlockProperty::GetList(
					array('SORT' => 'ASC', 'ID' => 'ASC'),
					array('IBLOCK_ID' => $skuIblockID, 'ACTIVE' => 'Y')
				);

				while ($arProp = $rsProps->Fetch())
				{
					if ($arProp['PROPERTY_TYPE'] == 'L' || $arProp['PROPERTY_TYPE'] == 'E' || ($arProp['PROPERTY_TYPE'] == 'S' && $arProp['USER_TYPE'] == 'directory'))
					{
						if ($arProp['XML_ID'] == 'CML2_LINK')
							continue;

						if (!in_array($arProp['CODE'], $arSkuProps))
							continue;

						$arValues = array();

						if ($arProp['PROPERTY_TYPE'] == 'L')
						{
							$arValues = array();
							$rsPropEnums = CIBlockProperty::GetPropertyEnum($arProp['ID'], array('SORT' => 'ASC', 'VALUE' => 'ASC'));
							while ($arEnum = $rsPropEnums->Fetch())
							{
								$arValues['n'.$arEnum['ID']] = array(
									'ID' => $arEnum['ID'],
									'NAME' => $arEnum['VALUE'],
									'PICT' => false
								);
							}
						}
						elseif ($arProp['PROPERTY_TYPE'] == 'E')
						{
							$rsPropEnums = CIBlockElement::GetList(
								array('SORT' => 'ASC', 'NAME' => 'ASC'),
								array('IBLOCK_ID' => $arProp['LINK_IBLOCK_ID'], 'ACTIVE' => 'Y'),
								false,
								false,
								array('ID', 'NAME', 'PREVIEW_PICTURE')
							);
							while ($arEnum = $rsPropEnums->Fetch())
							{
								$arEnum['PREVIEW_PICTURE'] = CFile::GetFileArray($arEnum['PREVIEW_PICTURE']);

								if (!is_array($arEnum['PREVIEW_PICTURE']))
								{
									$arEnum['PREVIEW_PICTURE'] = false;
								}

								if ($arEnum['PREVIEW_PICTURE'] !== false)
								{
									$productImg = CFile::ResizeImageGet($arEnum['PREVIEW_PICTURE'], array('width'=>80, 'height'=>80), BX_RESIZE_IMAGE_PROPORTIONAL, false, false);
									$arEnum['PREVIEW_PICTURE']['SRC'] = $productImg['src'];
								}

								$arValues['n'.$arEnum['ID']] = array(
									'ID' => $arEnum['ID'],
									'NAME' => $arEnum['NAME'],
									'SORT' => $arEnum['SORT'],
									'PICT' => $arEnum['PREVIEW_PICTURE'],
									'XML_ID' => $arEnum['NAME']
								);
							}

						}
						elseif ($arProp['PROPERTY_TYPE'] == 'S' && $arProp['USER_TYPE'] == 'directory')
						{
							if (self::$highLoadInclude === null)
								self::$highLoadInclude = Loader::includeModule('highloadblock');
							if (self::$highLoadInclude)
							{
								$hlblock = HL\HighloadBlockTable::getList(array("filter" => array("=TABLE_NAME" => $arProp["USER_TYPE_SETTINGS"]["TABLE_NAME"])))->fetch();
								if ($hlblock)
								{
									$entity = HL\HighloadBlockTable::compileEntity($hlblock);
									$entity_data_class = $entity->getDataClass();
									$fieldsList = $entity->getFields();
									$dataOrder = array();
									if (isset($fieldsList['UF_SORT']))
										$dataOrder['UF_SORT'] = 'ASC';
									$dataOrder['UF_NAME'] = 'ASC';

									$rsData = $entity_data_class::getList(array(
										'order' => $dataOrder
									));
									while ($arData = $rsData->fetch())
									{
										$arValues['n'.$arData['ID']] = array(
											'ID' => $arData['ID'],
											'NAME' => $arData['UF_NAME'],
											'SORT' => $arData['UF_SORT'],
											'FILE' => $arData['UF_FILE'],
											'PICT' => false,
											'XML_ID' => $arData['UF_XML_ID']
										);
									}
								}
							}
						}

						if (!empty($arValues) && is_array($arValues))
						{
							$arRes[$skuIblockID][$arProp['ID']] = array(
								'ID' => $arProp['ID'],
								'CODE' => $arProp['CODE'],
								'NAME' => $arProp['NAME'],
								'TYPE' => $arProp['PROPERTY_TYPE'],
								'USER_TYPE' => $arProp['USER_TYPE'],
								'VALUES' => $arValues
							);
						}
					}
				}
			}

			foreach ($arBasketItems as &$arItem)
			{
				if (!isset($arItem['MODULE']) || $arItem['MODULE'] != 'catalog')
					continue;
				if (isset($arItem["IBLOCK_ID"]) && (int)$arItem["IBLOCK_ID"] > 0 && isset($arRes[$arItem["IBLOCK_ID"]]))
				{
					$arItem["SKU_DATA"] = $arRes[$arItem["IBLOCK_ID"]];

					$arUsedValues = array();
					$arTmpRes = array();

					$arOfFilter = array(
						"IBLOCK_ID" => $arItem["IBLOCK_ID"],
						"PROPERTY_".$arSkuIblockID[$arItem["IBLOCK_ID"]]["SKU_PROPERTY_ID"] => $arParents[$arItem["PRODUCT_ID"]]["PRODUCT_ID"]
					);

					$rsOffers = CIBlockElement::GetList(
						array(),
						$arOfFilter,
						false,
						false,
						array("ID", "IBLOCK_ID")
					);
					while ($obOffer = $rsOffers->GetNextElement())
					{
						$productData = $obOffer->GetFields();
						$productId = $productData['ID'];
						unset($productData);
						$arProps = $obOffer->GetProperties();
						$currentSkuPropValues = array();
						foreach ($arProps as $propName => $propValue)
						{
							if (!in_array($propName, $arSkuProps) || !isset($propValue['VALUE']))
								continue;

							$propValue['VALUE'] = (string)$propValue['VALUE'];
							if ($propValue['VALUE'] == '')
								continue;
							if (!is_array($arUsedValues[$arItem["PRODUCT_ID"]][$propName]) || !in_array($propValue['VALUE'], $arUsedValues[$arItem["PRODUCT_ID"]][$propName]))
								$arUsedValues[$arItem["PRODUCT_ID"]][$propName][] = $propValue['VALUE'];

							$currentSkuPropValues[$propName] = array(
								'~CODE' => $propValue['~CODE'],
								'CODE' => $propValue['CODE'],
								'~NAME' => $propValue['~NAME'],
								'NAME' => $propValue['NAME'],
								'~VALUE' => $propValue['~VALUE'],
								'VALUE' => $propValue['VALUE'],
								'~SORT' => $propValue['~SORT'],
								'SORT' => $propValue['SORT'],
							);
						}
						unset($arProps, $propName, $propValue);

						if (isset($updateBasketProps[$productId]) && !empty($currentSkuPropValues))
						{
							foreach ($updateBasketProps[$productId] as $basketId => $updateCodes)
							{
								$basketKey = static::getBasketKeyById($arBasketItems, $basketId);
								if ($basketKey === false)
									continue;
								static::fillMissingProperties($arBasketItems[$basketKey]['PROPS'], $updateCodes, $currentSkuPropValues);
								unset($basketKey);
							}
							unset($basketId, $updateCodes);
						}
						unset($currentSkuPropValues);
						unset($productId);
					}
					unset($obOffer, $rsOffers);

					if (!empty($arUsedValues))
					{
						// add only used values to the item SKU_DATA
						foreach ($arRes[$arItem["IBLOCK_ID"]] as $propId => $arProp)
						{
							if (!array_key_exists($arProp["CODE"], $arUsedValues[$arItem["PRODUCT_ID"]]))
							{
								continue;
							}

							$arTmpRes['n'.$propId] = array();
							foreach ($arProp["VALUES"] as $valId => $arValue)
							{
								// properties of various type have different values in the used values data
								if (($arProp["TYPE"] == "L" && in_array($arValue["NAME"], $arUsedValues[$arItem["PRODUCT_ID"]][$arProp["CODE"]]))
									|| ($arProp["TYPE"] == "E" && in_array($arValue["ID"], $arUsedValues[$arItem["PRODUCT_ID"]][$arProp["CODE"]]))
									|| ($arProp["TYPE"] == "S" && in_array($arValue["XML_ID"], $arUsedValues[$arItem["PRODUCT_ID"]][$arProp["CODE"]]))
								)
								{
									if ($arProp["TYPE"] == "S")
									{
										if (!empty($arValue["FILE"]))
										{
											$arTmpFile = CFile::GetFileArray($arValue["FILE"]);
											if (!empty($arTmpFile))
											{
												$tmpImg = CFile::ResizeImageGet($arTmpFile, array('width'=>80, 'height'=>80), BX_RESIZE_IMAGE_PROPORTIONAL, false, false);
												$arValue['PICT']['SRC'] = $tmpImg['src'];
											}
										}
									}

									$arTmpRes['n'.$propId]["CODE"] = $arProp["CODE"];
									$arTmpRes['n'.$propId]["NAME"] = $arProp["NAME"];
									$arTmpRes['n'.$propId]["VALUES"][$valId] = $arValue;
								}
							}
						}
					}

					$arItem["SKU_DATA"] = $arTmpRes;
				}
			}

			unset($arItem);
		}

		return $arBasketItems;
	}

	public function getAvailableQuantity($arBasketItems)
	{
		if (empty($arBasketItems) || !is_array($arBasketItems))
			return array();
		if (self::$catalogIncluded === null)
			self::$catalogIncluded = Loader::includeModule('catalog');
		if (!self::$catalogIncluded)
			return false;

		$arElementId = array();
		$productMap = array();
		foreach ($arBasketItems as $key => $arItem)
		{
			$arElementId[$arItem['PRODUCT_ID']] = $arItem['PRODUCT_ID'];
			if (!isset($productMap[$arItem['PRODUCT_ID']]))
				$productMap[$arItem['PRODUCT_ID']] = array();
			$productMap[$arItem['PRODUCT_ID']][] = $key;
		}
		unset($key, $arItem);

		if (!empty($arElementId))
		{
			$productIterator = CCatalogProduct::GetList(
				array(),
				array('ID' => $arElementId),
				false,
				false,
				array('ID', 'QUANTITY')
			);
			while ($product = $productIterator->Fetch())
			{
				if (!isset($productMap[$product['ID']]))
					continue;
				foreach ($productMap[$product['ID']] as $key)
					$arBasketItems[$key]['AVAILABLE_QUANTITY'] = $product['QUANTITY'];
				unset($key);
			}
			unset($product, $productIterator);
		}
		unset($productMap, $arElementId);

		return $arBasketItems;
	}

	public function recalculateBasket($arPost)
	{
		global $USER;
		$arRes = array();

		if ($this->hideCoupon != "Y")
		{
			if (!empty($arPost['delete_coupon']))
			{
				$arRes['VALID_COUPON'] = DiscountCouponsManager::delete($arPost['delete_coupon']);
			}
			else
			{
				$oldUse = false;
				if (array_key_exists('coupon', $arPost))
				{
					$arPost['coupon'] = trim((string)$arPost['coupon']);
					if ($arPost['coupon'] != '')
					{
						$arRes['VALID_COUPON'] = DiscountCouponsManager::add($arPost['coupon']);
					}
					else
					{
						$oldUse = true;
					}
				}
				if ($oldUse)
				{
					if (!isset($arRes["VALID_COUPON"]) || $arRes["VALID_COUPON"] === false)
					{
						DiscountCouponsManager::clear(true);
					}
				}
			}
		}

		$arTmpItems = array();
		$dbItems = CSaleBasket::GetList(
			array("PRICE" => "DESC"),
			array(
				"FUSER_ID" => CSaleBasket::GetBasketUserID(),
				"LID" => SITE_ID,
				"ORDER_ID" => "NULL"
			),
			false,
			false,
			array(
				"ID", "NAME", "PRODUCT_PROVIDER_CLASS", "CALLBACK_FUNC", "MODULE", "PRODUCT_ID",
				"QUANTITY", "DELAY", "CAN_BUY", "CURRENCY", "SUBSCRIBE", "TYPE", "SET_PARENT_ID", "NOTES"
			)
		);
		while ($arItem = $dbItems->Fetch())
		{
			if (CSaleBasketHelper::isSetItem($arItem))
				continue;

			$arTmpItems[] = $arItem;
		}

		if (!empty($arTmpItems))
		{
			if (self::$catalogIncluded === null)
				self::$catalogIncluded = Loader::includeModule('catalog');
			if (self::$catalogIncluded)
				$arTmpItems = getRatio($arTmpItems);

			foreach ($arTmpItems as $arItem)
			{
				$isFloatQuantity = ((isset($arItem["MEASURE_RATIO"]) && floatval($arItem["MEASURE_RATIO"]) > 0 && $arItem["MEASURE_RATIO"] != 1)
					|| $this->quantityFloat == "Y") ? true : false;

				if (!isset($arPost["QUANTITY_".$arItem["ID"]]) || floatval($arPost["QUANTITY_".$arItem["ID"]]) <= 0)
				{
					$quantityTmp = ($isFloatQuantity === true) ? floatval($arItem["QUANTITY"]) : intval($arItem["QUANTITY"]);
				}
				else
				{
					$quantityTmp = ($isFloatQuantity === true) ? floatval($arPost["QUANTITY_".$arItem["ID"]]) : intval($arPost["QUANTITY_".$arItem["ID"]]);
				}

				$deleteTmp = ($arPost["DELETE_".$arItem["ID"]] == "Y") ? "Y" : "N";
				$delayTmp = ($arPost["DELAY_".$arItem["ID"]] == "Y") ? "Y" : "N";

				if ($arItem["CAN_BUY"] == "Y")
				{
					$res = $this->checkQuantity($arItem, $quantityTmp);

					if (!empty($res))
						$arRes["WARNING_MESSAGE"][] = $res["ERROR"];
				}

				if ($deleteTmp == "Y" && in_array("DELETE", $this->columns))
				{
					if ($arItem["SUBSCRIBE"] == "Y" && is_array($_SESSION["NOTIFY_PRODUCT"][$USER->GetID()]))
						unset($_SESSION["NOTIFY_PRODUCT"][$USER->GetID()][$arItem["PRODUCT_ID"]]);

					CSaleBasket::Delete($arItem["ID"]);
				}
				elseif ($arItem["DELAY"] == "N" && $arItem["CAN_BUY"] == "Y")
				{
					$arFields = array();

					if (in_array("QUANTITY", $this->columns))
						$arFields["QUANTITY"] = $quantityTmp;
					if (in_array("DELAY", $this->columns))
						$arFields["DELAY"] = $delayTmp;

					if (!empty($arFields)
						&&
							($arItem["QUANTITY"] != $arFields["QUANTITY"] && in_array("QUANTITY", $this->columns)
								|| $arItem["DELAY"] != $arFields["DELAY"] && in_array("DELAY", $this->columns))
						)
						CSaleBasket::Update($arItem["ID"], $arFields);
				}
				elseif ($arItem["DELAY"] == "Y" && $arItem["CAN_BUY"] == "Y")
				{
					$arFields = array();

					if (in_array("DELAY", $this->columns))
						$arFields["DELAY"] = $delayTmp;

					if (!empty($arFields)
						&&
							($arItem["DELAY"] != $arFields["DELAY"] && in_array("DELAY", $this->columns))
						)
						CSaleBasket::Update($arItem["ID"], $arFields);
				}
			}
		}

		return $arRes;
	}

	public function checkQuantity($arBasketItem, $desiredQuantity)
	{
		global $USER;
		$arResult = array();

		/** @var $productProvider IBXSaleProductProvider */
		if ($productProvider = CSaleBasket::GetProductProvider($arBasketItem))
		{
			$arFieldsTmp = $productProvider::GetProductData(array(
				"PRODUCT_ID" => $arBasketItem["PRODUCT_ID"],
				"QUANTITY"   => $desiredQuantity,
				"RENEWAL"    => "N",
				"USER_ID"    => $USER->GetID(),
				"SITE_ID"    => SITE_ID,
				"BASKET_ID" => $arBasketItem["ID"],
				"CHECK_QUANTITY" => "Y",
				"CHECK_PRICE" => "N",
				"NOTES" => $arBasketItem["NOTES"]
			));
		}
		elseif (isset($arBasketItem["CALLBACK_FUNC"]) && !empty($arBasketItem["CALLBACK_FUNC"]))
		{
			$arFieldsTmp = CSaleBasket::ExecuteCallbackFunction(
				$arBasketItem["CALLBACK_FUNC"],
				$arBasketItem["MODULE"],
				$arBasketItem["PRODUCT_ID"],
				$desiredQuantity,
				"N",
				$USER->GetID(),
				SITE_ID
			);
		}
		else
			return $arResult;

		if (empty($arFieldsTmp) || !isset($arFieldsTmp["QUANTITY"]))
		{
			$arResult["ERROR"] = Loc::getMessage("SBB_PRODUCT_NOT_AVAILABLE", array("#PRODUCT#" => $arBasketItem["NAME"]));
		}
		elseif ($desiredQuantity > doubleval($arFieldsTmp["QUANTITY"]))
		{
			$arResult["ERROR"] = Loc::getMessage("SBB_PRODUCT_NOT_ENOUGH_QUANTITY", array("#PRODUCT#" => $arBasketItem["NAME"], "#NUMBER#" => $desiredQuantity));
		}

		return $arResult;
	}

	/**
	 * @param array $itemProperties
	 * @param array $propertyCodes
	 * @return array
	 */
	protected static function getMissingPropertyCodes(array $itemProperties, array $propertyCodes)
	{
		if (empty($propertyCodes) || !is_array($propertyCodes))
			return array();
		if (empty($itemProperties))
			return $propertyCodes;
		$result = array_fill_keys($propertyCodes, true);
		foreach ($itemProperties as &$property)
		{
			if (empty($property) || !is_array($property))
				continue;
			if (!isset($property['CODE']))
				continue;
			$code = trim((string)$property['CODE']);
			if ($code == '')
				continue;
			if (isset($result[$code]))
				unset($result[$code]);
		}
		unset($property);

		return (!empty($result) ? array_keys($result) : array());
	}

	/**
	 * @param array $basket
	 * @param int $basketId
	 * @return bool|int|string
	 */
	protected static function getBasketKeyById(array $basket, $basketId)
	{
		$result = false;
		if (empty($basket) || !is_array($basket))
			return $result;
		$basketId = (int)$basketId;
		if ($basketId <= 0)
			return $result;
		foreach ($basket as $basketKey => $basketItem)
		{
			if (isset($basketItem['ID']) && $basketItem['ID'] == $basketId)
			{
				$result = $basketKey;
				break;
			}
		}
		unset($basketKey, $basketItem);

		return $result;
	}

	/**
	 * @param array $itemProperties
	 * @param array $missingCodes
	 * @param array $values
	 * @return void
	 */
	protected static function fillMissingProperties(array &$itemProperties, array $missingCodes, array $values)
	{
		if (empty($missingCodes) || !is_array($missingCodes))
			return;
		if (empty($values) || !is_array($values))
			return;
		foreach ($missingCodes as &$code)
		{
			if (!isset($values[$code]))
				continue;
			$found = false;
			foreach ($itemProperties as $existValue)
			{
				if (isset($existValue['CODE']) && $existValue['CODE'] == $code)
				{
					$found = true;
					break;
				}
			}
			unset($existValue);
			if (!$found)
				$itemProperties[] = $values[$code];
			unset($found);
		}
		unset($code);
	}
}