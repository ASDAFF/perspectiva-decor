<?
use Bitrix\Main,
	Bitrix\Main\Localization\Loc,
	Bitrix\Catalog;

Loc::loadMessages(__FILE__);

class CAllCatalog
{
	protected static $arCatalogCache = array();
	protected static $catalogVatCache = array();

	public static function CheckFields($ACTION, &$arFields, $ID = 0)
	{
		global $APPLICATION;

		$arMsg = array();
		$boolResult = true;

		if (array_key_exists('OFFERS', $arFields))
			unset($arFields['OFFERS']);
		$ID = (int)$ID;
		$arCatalog = false;
		if (0 < $ID)
			$arCatalog = CCatalog::GetByID($ID);
		if ($boolResult)
		{
			if (('UPDATE' == $ACTION) && (false == $arCatalog))
			{
				$boolResult = false;
				$arMsg[] = array('id' => 'ID','text' => Loc::getMessage('BT_MOD_CATALOG_ERR_UPDATE_BAD_ID'));
			}
		}

		if ($boolResult)
		{
			if ('ADD' == $ACTION || is_set($arFields,'IBLOCK_ID'))
			{
				if (!is_set($arFields,'IBLOCK_ID'))
				{
					$arMsg[] = array('id' => 'IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_IBLOCK_ID_FIELD_ABSENT'));
					$boolResult = false;
				}
				elseif((int)$arFields['IBLOCK_ID'] <= 0)
				{
					$arMsg[] = array('id' => 'IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_IBLOCK_ID_INVALID'));
					$boolResult = false;
				}
				else
				{
					$arFields['IBLOCK_ID'] = (int)$arFields['IBLOCK_ID'];
					$rsIBlocks = CIBlock::GetByID($arFields['IBLOCK_ID']);
					if (!($arIBlock = $rsIBlocks->Fetch()))
					{
						$arMsg[] = array('id' => 'IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_IBLOCK_ID_ABSENT'));
						$boolResult = false;
					}
				}
			}
			if ((is_set($arFields, "SUBSCRIPTION") || $ACTION=="ADD") && $arFields["SUBSCRIPTION"] != "Y")
				$arFields["SUBSCRIPTION"] = "N";
			if ((is_set($arFields, "YANDEX_EXPORT") || $ACTION=="ADD") && $arFields["YANDEX_EXPORT"] != "Y")
				$arFields["YANDEX_EXPORT"] = "N";

			if ((is_set($arFields,'VAT_ID') || ('ADD' == $ACTION)))
			{
				$arFields['VAT_ID'] = (int)$arFields['VAT_ID'];
				if ($arFields['VAT_ID'] <= 0)
					$arFields['VAT_ID'] = 0;
			}
		}

		if ($boolResult)
		{
			if ('ADD' == $ACTION)
			{
				if (!is_set($arFields, "PRODUCT_IBLOCK_ID"))
				{
					$arFields["PRODUCT_IBLOCK_ID"] = 0;
				}
				elseif (0 > (int)$arFields["PRODUCT_IBLOCK_ID"])
				{
					$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_INVALID'));
					$arFields["PRODUCT_IBLOCK_ID"] = 0;
					$boolResult = false;
				}
				elseif (0 < (int)$arFields["PRODUCT_IBLOCK_ID"])
				{
					$arFields["PRODUCT_IBLOCK_ID"] = (int)$arFields["PRODUCT_IBLOCK_ID"];
					$rsIBlocks = CIBlock::GetByID($arFields['PRODUCT_IBLOCK_ID']);
					if (!($arIBlock = $rsIBlocks->Fetch()))
					{
						$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_ABSENT'));
						$arFields["PRODUCT_IBLOCK_ID"] = 0;
						$boolResult = false;
					}
					else
					{
						if ($arFields["PRODUCT_IBLOCK_ID"] == $arFields['IBLOCK_ID'])
						{
							$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_SELF'));
							$arFields["PRODUCT_IBLOCK_ID"] = 0;
							$boolResult = false;
						}
					}
				}
				else
				{
					$arFields["PRODUCT_IBLOCK_ID"] = 0;
				}

				if (!is_set($arFields, "SKU_PROPERTY_ID"))
				{
					$arFields["SKU_PROPERTY_ID"] = 0;
				}
				elseif (0 > (int)$arFields["SKU_PROPERTY_ID"])
				{
					$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_ID_INVALID'));
					$arFields["SKU_PROPERTY_ID"] = 0;
					$boolResult = false;
				}
				else
				{
					$arFields["SKU_PROPERTY_ID"] = (int)$arFields["SKU_PROPERTY_ID"];
				}

				if ((0 < $arFields["PRODUCT_IBLOCK_ID"]) && (0 == $arFields['SKU_PROPERTY_ID']))
				{
					$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_WITHOUT_SKU_PROP'));
					$boolResult = false;
				}
				elseif ((0 == $arFields["PRODUCT_IBLOCK_ID"]) && (0 < $arFields['SKU_PROPERTY_ID']))
				{
					$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_WITHOUT_PRODUCT'));
					$boolResult = false;
				}
				elseif ((0 < $arFields["PRODUCT_IBLOCK_ID"]) && (0 < $arFields['SKU_PROPERTY_ID']))
				{
					$rsProps = CIBlockProperty::GetList(array(),array('IBLOCK_ID' => $arFields['IBLOCK_ID'],'ID' => $arFields['SKU_PROPERTY_ID'],'ACTIVE' => 'Y'));
					if ($arProp = $rsProps->Fetch())
					{
						if (('E' != $arProp['PROPERTY_TYPE']) || ($arFields["PRODUCT_IBLOCK_ID"] != $arProp['LINK_IBLOCK_ID']))
						{
							$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_WITHOUT_PRODUCT'));
							$boolResult = false;
						}
					}
					else
					{
						$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_NOT_FOUND'));
						$boolResult = false;
					}
				}
			}
			elseif ('UPDATE' == $ACTION)
			{
				$boolLocalFlag = (is_set($arFields,'PRODUCT_IBLOCK_ID') == is_set($arFields,'SKU_PROPERTY_ID'));
				if (!$boolLocalFlag)
				{
					$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_AND_SKU_PROPERTY_ID_NEED'));
					$boolResult = false;
				}
				else
				{
					if (is_set($arFields, 'PRODUCT_IBLOCK_ID'))
					{
						if (0 > (int)$arFields["PRODUCT_IBLOCK_ID"])
						{
							$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_INVALID'));
							$arFields["PRODUCT_IBLOCK_ID"] = 0;
							$boolResult = false;
						}
						elseif (0 < (int)$arFields["PRODUCT_IBLOCK_ID"])
						{
							$arFields["PRODUCT_IBLOCK_ID"] = (int)$arFields["PRODUCT_IBLOCK_ID"];
							$rsIBlocks = CIBlock::GetByID($arFields['PRODUCT_IBLOCK_ID']);
							if (!($arIBlock = $rsIBlocks->Fetch()))
							{
								$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_ABSENT'));
								$arFields["PRODUCT_IBLOCK_ID"] = 0;
								$boolResult = false;
							}
							else
							{
								if (0 < $ID && $arFields["PRODUCT_IBLOCK_ID"] == $ID)
								{
									$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_SELF'));
									$arFields["PRODUCT_IBLOCK_ID"] = 0;
									$boolResult = false;
								}
								else
								{
									if (is_set($arFields, 'IBLOCK_ID') && $arFields["PRODUCT_IBLOCK_ID"] == $arFields['IBLOCK_ID'])
									{
										$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_SELF'));
										$arFields["PRODUCT_IBLOCK_ID"] = 0;
										$boolResult = false;
									}
								}
							}
						}
					}

					if (is_set($arFields, 'SKU_PROPERTY_ID'))
					{
						if (0 > (int)$arFields["SKU_PROPERTY_ID"])
						{
							$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_ID_INVALID'));
							$arFields["SKU_PROPERTY_ID"] = 0;
							$boolResult = false;
						}
						else
						{
							$arFields["SKU_PROPERTY_ID"] = (int)$arFields["SKU_PROPERTY_ID"];
						}
					}
					if (is_set($arFields, 'PRODUCT_IBLOCK_ID') && is_set($arFields, 'SKU_PROPERTY_ID'))
					{
						if ((0 < $arFields["PRODUCT_IBLOCK_ID"]) && (0 == $arFields['SKU_PROPERTY_ID']))
						{
							$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_WITHOUT_SKU_PROP'));
							$boolResult = false;
						}
						elseif ((0 == $arFields["PRODUCT_IBLOCK_ID"]) && (0 < $arFields['SKU_PROPERTY_ID']))
						{
							$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_WITHOUT_PRODUCT'));
							$boolResult = false;
						}
						elseif ((0 < $arFields["PRODUCT_IBLOCK_ID"]) && (0 < $arFields['SKU_PROPERTY_ID']))
						{
							$rsProps = CIBlockProperty::GetList(array(),array('IBLOCK_ID' => $ID, 'ID' => $arFields['SKU_PROPERTY_ID'],'ACTIVE' => 'Y'));
							if ($arProp = $rsProps->Fetch())
							{
								if (('E' != $arProp['PROPERTY_TYPE']) || ($arFields["PRODUCT_IBLOCK_ID"] != $arProp['LINK_IBLOCK_ID']))
								{
									$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_WITHOUT_PRODUCT'));
									$boolResult = false;
								}
							}
							else
							{
								$arMsg[] = array('id' => 'SKU_PROPERTY_ID', "text" => Loc::getMessage('BT_MOD_CATALOG_ERR_SKU_PROP_NOT_FOUND'));
								$boolResult = false;
							}
						}
					}
				}
			}
		}

		if (!$boolResult)
		{
			$obError = new CAdminException($arMsg);
			$APPLICATION->ResetException();
			$APPLICATION->ThrowException($obError);
		}
		return $boolResult;
	}

	public static function GetByID($ID)
	{
		global $DB;

		$ID = (int)$ID;
		if ($ID <= 0)
			return false;

		if (isset(self::$arCatalogCache[$ID]))
		{
			return self::$arCatalogCache[$ID];
		}
		else
		{
			$strSql = "SELECT CI.*, I.ID as ID, I.IBLOCK_TYPE_ID, I.LID, I.NAME,
					OFFERS.IBLOCK_ID OFFERS_IBLOCK_ID, OFFERS.SKU_PROPERTY_ID OFFERS_PROPERTY_ID
				FROM
					b_catalog_iblock CI INNER JOIN b_iblock I ON CI.IBLOCK_ID = I.ID
					LEFT JOIN b_catalog_iblock OFFERS ON CI.IBLOCK_ID = OFFERS.PRODUCT_IBLOCK_ID
				WHERE
					CI.IBLOCK_ID = ".$ID;
			$db_res = $DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			if ($res = $db_res->Fetch())
			{
				$res["OFFERS"] = $res["PRODUCT_IBLOCK_ID"] ? "Y": "N";
				self::$arCatalogCache[$ID] = $res;
				if (defined('CATALOG_GLOBAL_VARS') && 'Y' == CATALOG_GLOBAL_VARS)
				{
					global $CATALOG_CATALOG_CACHE;
					$CATALOG_CATALOG_CACHE = self::$arCatalogCache;
				}
				return $res;
			}
		}
		return false;
	}

	public static function GetFilterOperation($key)
	{
		$arResult = array(
			'FIELD' => '',
			'NEGATIVE' => 'N',
			'OPERATION' => '',
			'OR_NULL' => 'N'
		);

		static $arDoubleModify = array(
			'>=' => '>=',
			'<=' => '<='
		);

		static $arOneModify = array(
			'>' => '>',
			'<' => '<',
			'@' => 'IN',
			'~' => 'LIKE',
			'%' => 'QUERY',
			'=' => '='
		);

		$key = (string)$key;
		if ($key == '')
			return false;
		if (0 == strncmp($key, '!', 1))
		{
			$arResult['NEGATIVE'] = 'Y';
			$key = substr($key, 1);
			if ($key == '')
				return false;
			if (0 == strncmp($key, '+', 1))
			{
				$arResult['OR_NULL'] = 'Y';
				$key = substr($key, 1);
			}
		}
		elseif (0 == strncmp($key, '+', 1))
		{
			$arResult['OR_NULL'] = 'Y';
			$key = substr($key, 1);
			if ($key == '')
				return false;
			if (0 == strncmp($key, '!', 1))
			{
				$arResult['NEGATIVE'] = 'Y';
				$key = substr($key, 1);
			}
		}
		if ($key == '')
			return false;
		$strKeyOp = substr($key, 0, 2);
		if ('' != $strKeyOp && isset($arDoubleModify[$strKeyOp]))
		{
			$arResult['OPERATION'] = $arDoubleModify[$strKeyOp];
			$arResult['FIELD'] = substr($key, 2);
			return $arResult;
		}
		$strKeyOp = substr($key, 0, 1);
		if ('' != $strKeyOp && isset($arOneModify[$strKeyOp]))
		{
			$arResult['OPERATION'] = $arOneModify[$strKeyOp];
			$arResult['FIELD'] = substr($key, 1);
			return $arResult;
		}
		$arResult['OPERATION'] = '=';
		$arResult['FIELD'] = $key;
		return $arResult;
	}

	public static function PrepareSql(&$arFields, $arOrder, $arFilter, $arGroupBy, $arSelectFields)
	{
		global $DB;

		$strSqlSelect = '';
		$strSqlFrom = '';
		$strSqlWhere = '';
		$strSqlGroupBy = '';
		$strSqlOrderBy = '';

		$sqlGroupByList = array();
		$sqlFrom = array();
		$sqlSelect = array();

		reset($arFields);
		$firstField = current($arFields);

		$strDBType = strtoupper($DB->type);
		$oracleEdition = ('ORACLE' == $strDBType);
		$highEdition = ($oracleEdition || 'MSSQL' == $strDBType);

		$arGroupByFunct = array(
			"COUNT" => true,
			"AVG" => true,
			"MIN" => true,
			"MAX" => true,
			"SUM" => true
		);

		// GROUP BY -->
		if (!empty($arGroupBy) && is_array($arGroupBy))
		{
			$arSelectFields = $arGroupBy;
			foreach ($arGroupBy as $key => $val)
			{
				$val = strtoupper($val);
				$key = strtoupper($key);
				if (isset($arFields[$val]) && !isset($arGroupByFunct[$key]))
				{
					$sqlGroupByList[] = $arFields[$val]["FIELD"];
					if (isset($arFields[$val]["FROM"]) && !empty($arFields[$val]["FROM"]))
					{
						$sqlFrom[$arFields[$val]["FROM"]] = true;
					}
				}
			}
		}
		if (!empty($sqlGroupByList))
			$strSqlGroupBy = implode(', ', $sqlGroupByList);
		// <-- GROUP BY

		// SELECT -->
		if (empty($arGroupBy) && is_array($arGroupBy))
		{
			$sqlSelect[] = 'COUNT(%%_DISTINCT_%% '.$firstField['FIELD'].') as CNT';
		}
		else
		{
			if (isset($arSelectFields) && !is_array($arSelectFields))
			{
				$arSelectFields = array($arSelectFields);
			}
			if (!empty($arSelectFields) && is_array($arSelectFields) && !in_array('*', $arSelectFields))
			{
				$arClearFields = array();
				foreach ($arSelectFields as $key => $val)
				{
					if (isset($arFields[$val]))
					{
						$arClearFields[$key] = $val;
					}
				}
				$arSelectFields = $arClearFields;
			}

			if (!isset($arSelectFields)
				|| empty($arSelectFields)
				|| in_array("*", $arSelectFields))
			{
				foreach ($arFields as $fieldKey => $fieldDescr)
				{
					if (isset($fieldDescr['WHERE_ONLY']) && 'Y' == $fieldDescr['WHERE_ONLY'])
					{
						continue;
					}
					switch ($fieldDescr['TYPE'])
					{
						case 'datetime':
						case 'date':
							if ($highEdition && isset($arOrder[$fieldKey]))
							{
								$sqlSelect[] = $fieldDescr['FIELD'].' as '.$fieldKey.'_X1';
							}
							$sqlSelect[] = $DB->DateToCharFunction(
								$fieldDescr['FIELD'],
								('datetime' == $fieldDescr['TYPE'] ? 'FULL' : 'SHORT')
							).' as '.$fieldKey;
							break;
						default:
							$sqlSelect[] = $fieldDescr['FIELD'].' as '.$fieldKey;
							break;
					}
					if (isset($fieldDescr['FROM']) && !empty($fieldDescr['FROM']))
					{
						$sqlFrom[$fieldDescr['FROM']] = true;
					}
				}
			}
			else
			{
				foreach ($arSelectFields as $key => $val)
				{
					$val = strtoupper($val);
					$key = strtoupper($key);
					if (isset($arFields[$val]))
					{
						if (isset($arGroupByFunct[$key]))
						{
							$sqlSelect[] = $key.'('.$arFields[$val]['FIELD'].') as '.$val;
						}
						else
						{
							switch ($arFields[$val]['TYPE'])
							{
								case 'datetime':
								case 'date':
									if ($highEdition && isset($arOrder[$val]))
									{
										$sqlSelect[] = $arFields[$val]['FIELD'].' as '.$val.'_X1';
									}
									$sqlSelect[] = $DB->DateToCharFunction(
										$arFields[$val]['FIELD'],
										('datetime' == $arFields[$val]['TYPE'] ? 'FULL' : 'SHORT')
									).' as '.$val;
									break;
								default:
									$sqlSelect[] = $arFields[$val]['FIELD'].' as '.$val;
									break;
							}
						}
						if (isset($arFields[$val]['FROM']) && !empty($arFields[$val]['FROM']))
						{
							$sqlFrom[$arFields[$val]['FROM']] = true;
						}
					}
				}
			}

			if (!empty($sqlGroupByList))
			{
				$sqlSelect[] = 'COUNT(%%_DISTINCT_%% '.$firstField['FIELD'].') as CNT';
			}
			else
			{
				$sqlSelect[0] = '%%_DISTINCT_%% '.$sqlSelect[0];
			}
		}
		// <-- SELECT

		// WHERE -->
		$arSqlSearch = array();

		$filter_keys = (!is_array($arFilter) ? array() : array_keys($arFilter));

		for ($i = 0, $intCount = count($filter_keys); $i < $intCount; $i++)
		{
			$vals = $arFilter[$filter_keys[$i]];
			$vals = (!is_array($vals) ? array($vals) : array_values($vals));

			$key = $filter_keys[$i];
			$key_res = CCatalog::GetFilterOperation($key);
			if (empty($key_res))
				continue;
			$key = $key_res["FIELD"];
			$strNegative = $key_res["NEGATIVE"];
			$strOperation = $key_res["OPERATION"];
			$strOrNull = $key_res["OR_NULL"];

			if ('' != $key && isset($arFields[$key]))
			{
				$arSqlSearch_tmp = array();

				if (!empty($vals))
				{
					if ($strOperation == "IN")
					{
						if (isset($arFields[$key]["WHERE"]))
						{
							$arSqlSearch_tmp1 = call_user_func_array(
									$arFields[$key]["WHERE"],
									array($vals, $key, $strOperation, $strNegative, $arFields[$key]["FIELD"], &$arFields, &$arFilter)
								);
							if ($arSqlSearch_tmp1 !== false)
								$arSqlSearch_tmp[] = $arSqlSearch_tmp1;
						}
						else
						{
							if ($arFields[$key]["TYPE"] == "int")
							{
								array_walk($vals, create_function("&\$item", "\$item=(int)\$item;"));
								$vals = array_unique($vals);
								$val = implode(",", $vals);

								if (empty($vals))
									$arSqlSearch_tmp[] = "(1 = 2)";
								else
									$arSqlSearch_tmp[] = (($strNegative == "Y") ? " NOT " : "")."(".$arFields[$key]["FIELD"]." IN (".$val."))";
							}
							elseif ($arFields[$key]["TYPE"] == "double")
							{
								array_walk($vals, create_function("&\$item", "\$item=(float)\$item;"));
								$vals = array_unique($vals);
								$val = implode(",", $vals);

								if (empty($vals))
									$arSqlSearch_tmp[] = "(1 = 2)";
								else
									$arSqlSearch_tmp[] = (($strNegative == "Y") ? " NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." (".$val."))";
							}
							elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
							{
								array_walk($vals, create_function("&\$item", "\$item=\"'\".\$GLOBALS[\"DB\"]->ForSql(\$item).\"'\";"));
								$vals = array_unique($vals);
								$val = implode(",", $vals);

								if (empty($vals))
									$arSqlSearch_tmp[] = "(1 = 2)";
								else
									$arSqlSearch_tmp[] = (($strNegative == "Y") ? " NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." (".$val."))";
							}
							elseif ($arFields[$key]["TYPE"] == "datetime")
							{
								array_walk($vals, create_function("&\$item", "\$item=\"'\".\$GLOBALS[\"DB\"]->CharToDateFunction(\$GLOBALS[\"DB\"]->ForSql(\$item), \"FULL\").\"'\";"));
								$vals = array_unique($vals);
								$val = implode(",", $vals);

								if (empty($vals))
									$arSqlSearch_tmp[] = "1 = 2";
								else
									$arSqlSearch_tmp[] = ($strNegative=="Y"?" NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." (".$val."))";
							}
							elseif ($arFields[$key]["TYPE"] == "date")
							{
								array_walk($vals, create_function("&\$item", "\$item=\"'\".\$GLOBALS[\"DB\"]->CharToDateFunction(\$GLOBALS[\"DB\"]->ForSql(\$item), \"SHORT\").\"'\";"));
								$vals = array_unique($vals);
								$val = implode(",", $vals);

								if (empty($vals))
									$arSqlSearch_tmp[] = "1 = 2";
								else
									$arSqlSearch_tmp[] = ($strNegative=="Y"?" NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." (".$val."))";
							}
						}
					}
					else
					{
						for ($j = 0, $intCountVals = count($vals); $j < $intCountVals; $j++)
						{
							$val = $vals[$j];

							if (isset($arFields[$key]["WHERE"]))
							{
								$arSqlSearch_tmp1 = call_user_func_array(
										$arFields[$key]["WHERE"],
										array($val, $key, $strOperation, $strNegative, $arFields[$key]["FIELD"], &$arFields, &$arFilter)
									);
								if ($arSqlSearch_tmp1 !== false)
									$arSqlSearch_tmp[] = $arSqlSearch_tmp1;
							}
							else
							{
								if ($arFields[$key]["TYPE"] == "int")
								{
									if ((int)$val == 0 && strpos($strOperation, "=") !== false)
										$arSqlSearch_tmp[] = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND" : "OR")." ".(($strNegative == "Y") ? "NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." 0)";
									else
										$arSqlSearch_tmp[] = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".(int)$val." )";
								}
								elseif ($arFields[$key]["TYPE"] == "double")
								{
									$val = str_replace(",", ".", $val);

									if ((float)$val == 0 && strpos($strOperation, "=") !== false)
										$arSqlSearch_tmp[] = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND" : "OR")." ".(($strNegative == "Y") ? "NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." 0)";
									else
										$arSqlSearch_tmp[] = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".(float)$val." )";
								}
								elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
								{
									if ($strOperation == "QUERY")
									{
										$arSqlSearch_tmp[] = GetFilterQuery($arFields[$key]["FIELD"], $val, "Y");
									}
									else
									{
										if ((strlen($val) == 0) && (strpos($strOperation, "=") !== false))
											$arSqlSearch_tmp[] = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND NOT" : "OR")." (".$DB->Length($arFields[$key]["FIELD"])." <= 0) ".(($strNegative == "Y") ? "AND NOT" : "OR")." (".$arFields[$key]["FIELD"]." ".$strOperation." '".$DB->ForSql($val)."' )";
										else
											$arSqlSearch_tmp[] = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." '".$DB->ForSql($val)."' )";
									}
								}
								elseif ($arFields[$key]["TYPE"] == "datetime")
								{
									if (strlen($val) <= 0)
										$arSqlSearch_tmp[] = ($strNegative=="Y"?"NOT":"")."(".$arFields[$key]["FIELD"]." IS NULL)";
									else
										$arSqlSearch_tmp[] = ($strNegative=="Y"?" ".$arFields[$key]["FIELD"]." IS NULL OR NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".$DB->CharToDateFunction($DB->ForSql($val), "FULL").")";
								}
								elseif ($arFields[$key]["TYPE"] == "date")
								{
									if (strlen($val) <= 0)
										$arSqlSearch_tmp[] = ($strNegative=="Y"?"NOT":"")."(".$arFields[$key]["FIELD"]." IS NULL)";
									else
										$arSqlSearch_tmp[] = ($strNegative=="Y"?" ".$arFields[$key]["FIELD"]." IS NULL OR NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".$DB->CharToDateFunction($DB->ForSql($val), "SHORT").")";
								}
							}
						}
					}
				}

				if (isset($arFields[$key]["FROM"]) && !empty($arFields[$key]["FROM"]))
				{
					$sqlFrom[$arFields[$key]["FROM"]] = true;
				}

				$strSqlSearch_tmp = "";
				for ($j = 0, $intCountSearch = count($arSqlSearch_tmp); $j < $intCountSearch; $j++)
				{
					if ($j > 0)
						$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlSearch_tmp .= "(".$arSqlSearch_tmp[$j].")";
				}
				if ($strOrNull == "Y")
				{
					if (strlen($strSqlSearch_tmp) > 0)
						$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." IS ".($strNegative=="Y" ? "NOT " : "")."NULL)";

					if ($arFields[$key]["TYPE"] == "int" || $arFields[$key]["TYPE"] == "double")
					{
						if (strlen($strSqlSearch_tmp) > 0)
							$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
						$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." 0)";
					}
					elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
					{
						if (strlen($strSqlSearch_tmp) > 0)
							$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
						$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." '')";
					}
				}

				if ($strSqlSearch_tmp != "")
					$arSqlSearch[] = "(".$strSqlSearch_tmp.")";
			}
		}

		if (!empty($arSqlSearch))
			$strSqlWhere = '('.implode(') and (', $arSqlSearch).')';
		// <-- WHERE

		// ORDER BY -->
		$arSqlOrder = array();
		$sortExist = array();
		foreach ($arOrder as $by => $order)
		{
			$by = strtoupper($by);
			$order = strtoupper($order);

			if ($order != 'ASC')
				$order = 'DESC';
			if ($oracleEdition)
			{
				$order .= ($order == 'ASC' ? ' NULLS FIRST' : ' NULLS LAST');
			}

			if (isset($arFields[$by]))
			{
				if (isset($sortExist[$by]))
					continue;
				$sortExist[$by] = true;
				$arSqlOrder[] = $arFields[$by]["FIELD"].' '.$order;
				if (isset($arFields[$by]["FROM"]) && !empty($arFields[$by]["FROM"]))
				{
					$sqlFrom[$arFields[$by]["FROM"]] = true;
				}
			}
		}
		if (!empty($arSqlOrder))
		{
			$strSqlOrderBy = implode (', ', $arSqlOrder);
		}
		// <-- ORDER BY

		$sqlFromTables = array();
		if (!empty($sqlFrom))
		{
			$sqlFromTables = array_keys($sqlFrom);
			$strSqlFrom = implode(' ', $sqlFromTables);
		}

		if (!empty($sqlSelect))
		{
			$strSqlSelect = implode(', ', $sqlSelect);
		}

		return array(
			'SELECT' => $strSqlSelect,
			'FROM' => $strSqlFrom,
			'WHERE' => $strSqlWhere,
			'GROUPBY' => $strSqlGroupBy,
			'ORDERBY' => $strSqlOrderBy,
			'SELECT_FIELDS' => $sqlSelect,
			'FROM_TABLES' => $sqlFromTables,
			'GROUPBY_FIELDS' => $sqlGroupByList,
			'ORDERBY_FIELDS' => array_keys($sortExist)
		);
	}

	public static function _PrepareSql(&$arFields, $arOrder, $arFilter, $arGroupBy, $arSelectFields)
	{
		global $DB;

		$strSqlSelect = "";
		$strSqlFrom = "";
		$strSqlWhere = "";
		$strSqlGroupBy = "";

		$sqlGroupByList = array();

		$strDBType = strtoupper($DB->type);
		$oracleEdition = ('ORACLE' == $strDBType);
		$highEdition = ($oracleEdition || 'MSSQL' == $strDBType);

		$arGroupByFunct = array(
			"COUNT" => true,
			"AVG" => true,
			"MIN" => true,
			"MAX" => true,
			"SUM" => true
		);

		$arAlreadyJoined = array();

		// GROUP BY -->
		if (!empty($arGroupBy) && is_array($arGroupBy))
		{
			foreach ($arGroupBy as $key => $val)
			{
				$val = strtoupper($val);
				$key = strtoupper($key);
				if (isset($arFields[$val]) && !isset($arGroupByFunct[$key]))
				{
					$sqlGroupByList[] = $arFields[$val]["FIELD"];

					if (isset($arFields[$val]["FROM"])
						&& strlen($arFields[$val]["FROM"]) > 0
						&& !in_array($arFields[$val]["FROM"], $arAlreadyJoined))
					{
						if (strlen($strSqlFrom) > 0)
							$strSqlFrom .= " ";
						$strSqlFrom .= $arFields[$val]["FROM"];
						$arAlreadyJoined[] = $arFields[$val]["FROM"];
					}
				}
			}
		}
		if (!empty($sqlGroupByList))
			$strSqlGroupBy = implode(', ', $sqlGroupByList);
		// <-- GROUP BY

		// SELECT -->
		$arFieldsKeys = array_keys($arFields);

		if (empty($arGroupBy) && is_array($arGroupBy))
		{
			$strSqlSelect = "COUNT(%%_DISTINCT_%% ".$arFields[$arFieldsKeys[0]]["FIELD"].") as CNT ";
		}
		else
		{
			if (
				isset($arSelectFields)
				&& is_string($arSelectFields)
				&& '' != $arSelectFields
				&& isset($arFields[$arSelectFields])
			)
			{
				$arSelectFields = array($arSelectFields);
			}

			if (!isset($arSelectFields)
				|| empty($arSelectFields)
				|| !is_array($arSelectFields)
				|| in_array("*", $arSelectFields))
			{
				for ($i = 0, $intCount = count($arFieldsKeys); $i < $intCount; $i++)
				{
					if (isset($arFields[$arFieldsKeys[$i]]["WHERE_ONLY"])
						&& $arFields[$arFieldsKeys[$i]]["WHERE_ONLY"] == "Y")
					{
						continue;
					}

					if ('' != $strSqlSelect)
						$strSqlSelect .= ", ";

					if ($arFields[$arFieldsKeys[$i]]["TYPE"] == "datetime")
					{
						if ($highEdition && isset($arOrder[$arFieldsKeys[$i]]))
							$strSqlSelect .= $arFields[$arFieldsKeys[$i]]["FIELD"]." as ".$arFieldsKeys[$i]."_X1, ";

						$strSqlSelect .= $DB->DateToCharFunction($arFields[$arFieldsKeys[$i]]["FIELD"], "FULL")." as ".$arFieldsKeys[$i];
					}
					elseif ($arFields[$arFieldsKeys[$i]]["TYPE"] == "date")
					{
						if ($highEdition && isset($arOrder[$arFieldsKeys[$i]]))
							$strSqlSelect .= $arFields[$arFieldsKeys[$i]]["FIELD"]." as ".$arFieldsKeys[$i]."_X1, ";

						$strSqlSelect .= $DB->DateToCharFunction($arFields[$arFieldsKeys[$i]]["FIELD"], "SHORT")." as ".$arFieldsKeys[$i];
					}
					else
						$strSqlSelect .= $arFields[$arFieldsKeys[$i]]["FIELD"]." as ".$arFieldsKeys[$i];

					if (isset($arFields[$arFieldsKeys[$i]]["FROM"])
						&& strlen($arFields[$arFieldsKeys[$i]]["FROM"]) > 0
						&& !in_array($arFields[$arFieldsKeys[$i]]["FROM"], $arAlreadyJoined))
					{
						if (strlen($strSqlFrom) > 0)
							$strSqlFrom .= " ";
						$strSqlFrom .= $arFields[$arFieldsKeys[$i]]["FROM"];
						$arAlreadyJoined[] = $arFields[$arFieldsKeys[$i]]["FROM"];
					}
				}
			}
			else
			{
				foreach ($arSelectFields as $key => $val)
				{
					$val = strtoupper($val);
					$key = strtoupper($key);
					if (isset($arFields[$val]))
					{
						if ('' != $strSqlSelect)
							$strSqlSelect .= ", ";

						if (isset($arGroupByFunct[$key]))
						{
							$strSqlSelect .= $key."(".$arFields[$val]["FIELD"].") as ".$val;
						}
						else
						{
							if ($arFields[$val]["TYPE"] == "datetime")
							{
								if ($highEdition && isset($arOrder[$val]))
									$strSqlSelect .= $arFields[$val]["FIELD"]." as ".$val."_X1, ";

								$strSqlSelect .= $DB->DateToCharFunction($arFields[$val]["FIELD"], "FULL")." as ".$val;
							}
							elseif ($arFields[$val]["TYPE"] == "date")
							{
								if ($highEdition && isset($arOrder[$val]))
									$strSqlSelect .= $arFields[$val]["FIELD"]." as ".$val."_X1, ";

								$strSqlSelect .= $DB->DateToCharFunction($arFields[$val]["FIELD"], "SHORT")." as ".$val;
							}
							else
								$strSqlSelect .= $arFields[$val]["FIELD"]." as ".$val;
						}

						if (isset($arFields[$val]["FROM"])
							&& strlen($arFields[$val]["FROM"]) > 0
							&& !in_array($arFields[$val]["FROM"], $arAlreadyJoined))
						{
							if ('' != $strSqlFrom)
								$strSqlFrom .= " ";
							$strSqlFrom .= $arFields[$val]["FROM"];
							$arAlreadyJoined[] = $arFields[$val]["FROM"];
						}
					}
				}
			}

			if ('' != $strSqlGroupBy)
			{
				if ('' != $strSqlSelect)
					$strSqlSelect .= ", ";
				$strSqlSelect .= "COUNT(%%_DISTINCT_%% ".$arFields[$arFieldsKeys[0]]["FIELD"].") as CNT";
			}
			else
			{
				$strSqlSelect = "%%_DISTINCT_%% ".$strSqlSelect;
			}
		}
		// <-- SELECT

		// WHERE -->
		$arSqlSearch = Array();
		$arSqlHaving = Array();

		$filter_keys = (!is_array($arFilter) ? array() : array_keys($arFilter));

		for ($i = 0, $intCount = count($filter_keys); $i < $intCount; $i++)
		{
			$vals = $arFilter[$filter_keys[$i]];
			$vals = (!is_array($vals) ? array($vals) : array_values($vals));

			$key = $filter_keys[$i];
			$key_res = CCatalog::GetFilterOperation($key);
			if (empty($key_res))
				continue;
			$key = $key_res["FIELD"];
			$strNegative = $key_res["NEGATIVE"];
			$strOperation = $key_res["OPERATION"];
			$strOrNull = $key_res["OR_NULL"];

			if ('' != $key && isset($arFields[$key]))
			{
				$arSqlSearch_tmp = array();
				$arSqlHaving_tmp = array();
				for ($j = 0, $intCountVals = count($vals); $j < $intCountVals; $j++)
				{
					$val = $vals[$j];

					if (isset($arFields[$key]["WHERE"]))
					{
						$arSqlSearch_tmp1 = call_user_func_array(
								$arFields[$key]["WHERE"],
								array($val, $key, $strOperation, $strNegative, $arFields[$key]["FIELD"], &$arFields, &$arFilter)
							);
						if ($arSqlSearch_tmp1 !== false)
						{
							if (isset($arFields[$key]["GROUPED"]) && $arFields[$key]["GROUPED"] == "Y")
								$arSqlHaving_tmp[] = $arSqlSearch_tmp1;
							else
								$arSqlSearch_tmp[] = $arSqlSearch_tmp1;
						}
					}
					else
					{
						$arSqlSearch_tmp1 = "";

						if ($arFields[$key]["TYPE"] == "int")
						{
							if ((int)$val == 0 && strpos($strOperation, "=") !== false)
								$arSqlSearch_tmp1 = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND" : "OR")." ".(($strNegative == "Y") ? "NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." 0)";
							else
								$arSqlSearch_tmp1 = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".(int)$val." )";
						}
						elseif ($arFields[$key]["TYPE"] == "double")
						{
							$val = str_replace(",", ".", $val);

							if ((float)$val == 0 && strpos($strOperation, "=") !== false)
								$arSqlSearch_tmp1 = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND" : "OR")." ".(($strNegative == "Y") ? "NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." 0)";
							else
								$arSqlSearch_tmp1 = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".(float)$val." )";
						}
						elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
						{
							if ($strOperation == "QUERY")
							{
								$arSqlSearch_tmp1 = GetFilterQuery($arFields[$key]["FIELD"], $val, "Y");
							}
							else
							{
								if ((strlen($val) == 0) && (strpos($strOperation, "=") !== false))
									$arSqlSearch_tmp1 = "(".$arFields[$key]["FIELD"]." IS ".(($strNegative == "Y") ? "NOT " : "")."NULL) ".(($strNegative == "Y") ? "AND NOT" : "OR")." (".$DB->Length($arFields[$key]["FIELD"])." <= 0) ".(($strNegative == "Y") ? "AND NOT" : "OR")." (".$arFields[$key]["FIELD"]." ".$strOperation." '".$DB->ForSql($val)."' )";
								else
									$arSqlSearch_tmp1 = (($strNegative == "Y") ? " ".$arFields[$key]["FIELD"]." IS NULL OR NOT " : "")."(".$arFields[$key]["FIELD"]." ".$strOperation." '".$DB->ForSql($val)."' )";
							}
						}
						elseif ($arFields[$key]["TYPE"] == "datetime")
						{
							if (strlen($val) <= 0)
								$arSqlSearch_tmp1 = ($strNegative=="Y"?"NOT":"")."(".$arFields[$key]["FIELD"]." IS NULL)";
							else
								$arSqlSearch_tmp1 = ($strNegative=="Y"?" ".$arFields[$key]["FIELD"]." IS NULL OR NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".$DB->CharToDateFunction($DB->ForSql($val), "FULL").")";
						}
						elseif ($arFields[$key]["TYPE"] == "date")
						{
							if (strlen($val) <= 0)
								$arSqlSearch_tmp1 = ($strNegative=="Y"?"NOT":"")."(".$arFields[$key]["FIELD"]." IS NULL)";
							else
								$arSqlSearch_tmp1 = ($strNegative=="Y"?" ".$arFields[$key]["FIELD"]." IS NULL OR NOT ":"")."(".$arFields[$key]["FIELD"]." ".$strOperation." ".$DB->CharToDateFunction($DB->ForSql($val), "SHORT").")";
						}

						if (isset($arFields[$key]["GROUPED"]) && $arFields[$key]["GROUPED"] == "Y")
							$arSqlHaving_tmp[] = $arSqlSearch_tmp1;
						else
							$arSqlSearch_tmp[] = $arSqlSearch_tmp1;
					}
				}

				if (isset($arFields[$key]["FROM"])
					&& strlen($arFields[$key]["FROM"]) > 0
					&& !in_array($arFields[$key]["FROM"], $arAlreadyJoined))
				{
					if (strlen($strSqlFrom) > 0)
						$strSqlFrom .= " ";
					$strSqlFrom .= $arFields[$key]["FROM"];
					$arAlreadyJoined[] = $arFields[$key]["FROM"];
				}

				$strSqlSearch_tmp = "";
				for ($j = 0, $intCountSearchTmp = count($arSqlSearch_tmp); $j < $intCountSearchTmp; $j++)
				{
					if ($j > 0)
						$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlSearch_tmp .= "(".$arSqlSearch_tmp[$j].")";
				}
				if ($strOrNull == "Y")
				{
					if (strlen($strSqlSearch_tmp) > 0)
						$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." IS ".($strNegative=="Y" ? "NOT " : "")."NULL)";

					if ($arFields[$key]["TYPE"] == "int" || $arFields[$key]["TYPE"] == "double")
					{
						if (strlen($strSqlSearch_tmp) > 0)
							$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
						$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." 0)";
					}
					elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
					{
						if (strlen($strSqlSearch_tmp) > 0)
							$strSqlSearch_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
						$strSqlSearch_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." '')";
					}
				}

				if ($strSqlSearch_tmp != "")
					$arSqlSearch[] = "(".$strSqlSearch_tmp.")";

				$strSqlHaving_tmp = "";
				for ($j = 0, $intCountHavingTmp = count($arSqlHaving_tmp); $j < $intCountHavingTmp; $j++)
				{
					if ($j > 0)
						$strSqlHaving_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlHaving_tmp .= "(".$arSqlHaving_tmp[$j].")";
				}
				if ($strOrNull == "Y")
				{
					if (strlen($strSqlHaving_tmp) > 0)
						$strSqlHaving_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					$strSqlHaving_tmp .= "(".$arFields[$key]["FIELD"]." IS ".($strNegative=="Y" ? "NOT " : "")."NULL)";

					if (strlen($strSqlHaving_tmp) > 0)
						$strSqlHaving_tmp .= ($strNegative=="Y" ? " AND " : " OR ");
					if ($arFields[$key]["TYPE"] == "int" || $arFields[$key]["TYPE"] == "double")
						$strSqlHaving_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." 0)";
					elseif ($arFields[$key]["TYPE"] == "string" || $arFields[$key]["TYPE"] == "char")
						$strSqlHaving_tmp .= "(".$arFields[$key]["FIELD"]." ".($strNegative=="Y" ? "<>" : "=")." '')";
				}

				if ($strSqlHaving_tmp != "")
					$arSqlHaving[] = "(".$strSqlHaving_tmp.")";
			}
		}

		if (!empty($arSqlSearch))
			$strSqlWhere = '('.implode(') and (', $arSqlSearch).')';

		$strSqlHaving = '';
		if (!empty($arSqlHaving))
			$strSqlHaving = '('.implode(') and (', $arSqlHaving).')';
		// <-- WHERE

		// ORDER BY -->
		$arSqlOrder = array();
		foreach ($arOrder as $by => $order)
		{
			$by = strtoupper($by);
			$order = strtoupper($order);

			if ($order != "ASC")
				$order = "DESC".($oracleEdition ? " NULLS LAST" : "");
			else
				$order = "ASC".($oracleEdition ? " NULLS FIRST" : "");

			if (isset($arFields[$by]))
			{
				$arSqlOrder[] = " ".$arFields[$by]["FIELD"]." ".$order." ";

				if (isset($arFields[$by]["FROM"])
					&& strlen($arFields[$by]["FROM"]) > 0
					&& !in_array($arFields[$by]["FROM"], $arAlreadyJoined))
				{
					if (strlen($strSqlFrom) > 0)
						$strSqlFrom .= " ";
					$strSqlFrom .= $arFields[$by]["FROM"];
					$arAlreadyJoined[] = $arFields[$by]["FROM"];
				}
			}
		}

		$strSqlOrder = '';
		DelDuplicateSort($arSqlOrder);
		if (!empty($arSqlOrder))
			$strSqlOrder = implode(', ', $arSqlOrder);
		// <-- ORDER BY

		return array(
			"SELECT" => $strSqlSelect,
			"FROM" => $strSqlFrom,
			"WHERE" => $strSqlWhere,
			"GROUPBY" => $strSqlGroupBy,
			"ORDERBY" => $strSqlOrder,
			"HAVING" => $strSqlHaving
		);
	}

	public static function Add($arFields)
	{
		global $DB;

		if (!CCatalog::CheckFields("ADD", $arFields, 0))
			return false;

		$arInsert = $DB->PrepareInsert("b_catalog_iblock", $arFields);

		$strSql = "INSERT INTO b_catalog_iblock(".$arInsert[0].") VALUES(".$arInsert[1].")";
		$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);

		CCatalogSKU::ClearCache();

		return true;
	}

	public static function Update($ID, $arFields)
	{
		global $DB;
		$ID = (int)$ID;

		if (!CCatalog::CheckFields("UPDATE", $arFields, $ID))
			return false;

		$strUpdate = $DB->PrepareUpdate("b_catalog_iblock", $arFields);
		if (!empty($strUpdate))
		{
			$strSql = "UPDATE b_catalog_iblock SET ".$strUpdate." WHERE IBLOCK_ID = ".$ID;
			$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);

			if (isset(self::$arCatalogCache[$ID]))
			{
				unset(self::$arCatalogCache[$ID]);
				if (defined('CATALOG_GLOBAL_VARS') && 'Y' == CATALOG_GLOBAL_VARS)
				{
					global $CATALOG_CATALOG_CACHE;
					$CATALOG_CATALOG_CACHE = self::$arCatalogCache;
				}
			}
			if (isset(self::$catalogVatCache[$ID]))
				unset(self::$catalogVatCache[$ID]);
		}
		CCatalogSKU::ClearCache();
		return true;
	}

	public static function Delete($ID)
	{
		global $DB;
		$ID = (int)$ID;

		foreach(GetModuleEvents("catalog", "OnBeforeCatalogDelete", true) as $arEvent)
		{
			if (ExecuteModuleEventEx($arEvent, array($ID))===false)
				return false;
		}

		foreach(GetModuleEvents("catalog", "OnCatalogDelete", true) as $arEvent)
			ExecuteModuleEventEx($arEvent, array($ID));

		if (isset(self::$arCatalogCache[$ID]))
		{
			unset(self::$arCatalogCache[$ID]);
			if (defined('CATALOG_GLOBAL_VARS') && CATALOG_GLOBAL_VARS == 'Y')
			{
				global $CATALOG_CATALOG_CACHE;
				$CATALOG_CATALOG_CACHE = self::$arCatalogCache;
			}
		}
		if (isset(self::$catalogVatCache[$ID]))
		{
			unset(self::$catalogVatCache[$ID]);
		}
		CCatalogSKU::ClearCache();
		CCatalogProduct::ClearCache();
		return $DB->Query("DELETE FROM b_catalog_iblock WHERE IBLOCK_ID = ".$ID, true);
	}

	public static function OnIBlockDelete($ID)
	{
		return CCatalog::Delete($ID);
	}

	public static function PreGenerateXML($xml_type = 'yandex')
	{
		if ($xml_type == 'yandex')
		{
			$strYandexAgent = (string)Main\Config\Option::get('catalog','yandex_agent_file');
			if ($strYandexAgent != '')
			{
				if (file_exists($_SERVER['DOCUMENT_ROOT'].$strYandexAgent) && is_file($_SERVER['DOCUMENT_ROOT'].$strYandexAgent))
				{
					include_once($_SERVER['DOCUMENT_ROOT'].$strYandexAgent);
				}
				else
				{
					CEventLog::Log('WARNING','CAT_YAND_FILE','catalog','YandexAgent',$strYandexAgent);
					include_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/catalog/load/yandex.php");
				}
			}
			else
			{
				include_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/catalog/load/yandex.php");
			}
		}

		global $pPERIOD;
		$pPERIOD = (int)Main\Config\Option::get('catalog', 'yandex_xml_period')*3600;
		return 'CCatalog::PreGenerateXML("'.$xml_type.'");';
	}

/*
* @deprecated deprecated since catalog 11.0.2
* @see CCatalogSKU::GetInfoByProductIBlock()
*/
	public static function GetSkuInfoByProductID($ID)
	{
		return CCatalogSKU::GetInfoByProductIBlock($ID);
	}

/*
* @deprecated deprecated since catalog 11.0.2
* @see CCatalogSKU::GetInfoByLinkProperty()
*/
	public static function GetSkuInfoByPropID($ID)
	{
		return CCatalogSKU::GetInfoByLinkProperty($ID);
	}

	public static function OnBeforeIBlockElementDelete($ID)
	{
		global $APPLICATION;

		$ID = (int)$ID;
		if (0 < $ID)
		{
			$intIBlockID = (int)CIBlockElement::GetIBlockByID($ID);
			if (0 < $intIBlockID)
			{
				$arCatalog = CCatalogSKU::GetInfoByProductIBlock($intIBlockID);
				if (!empty($arCatalog) && is_array($arCatalog) && 0 < $arCatalog['IBLOCK_ID'] && 0 < $arCatalog['SKU_PROPERTY_ID'])
				{
					$arFilter = array('IBLOCK_ID' => $arCatalog['IBLOCK_ID'],'=PROPERTY_'.$arCatalog['SKU_PROPERTY_ID'] => $ID);
					$rsOffers = CIBlockElement::GetList(array(), $arFilter, false, false, array('ID', 'IBLOCK_ID'));
					while($arOffer = $rsOffers->Fetch())
					{
						foreach(GetModuleEvents("iblock", "OnBeforeIBlockElementDelete", true) as $arEvent)
						{
							if (ExecuteModuleEventEx($arEvent, array($arOffer['ID']))===false)
							{
								$err = Loc::getMessage("BT_MOD_CATALOG_ERR_BEFORE_DEL_TITLE").' '.$arEvent['TO_NAME'];
								$err_id = false;
								if ($ex = $APPLICATION->GetException())
								{
									$err .= ': '.$ex->GetString();
									$err_id = $ex->GetID();
								}
								$APPLICATION->ThrowException($err, $err_id);
								return false;
							}
						}
						if (!CIBlockElement::Delete($arOffer['ID']))
						{
							$APPLICATION->ThrowException(Loc::getMessage('BT_MOD_CATALOG_ERR_CANNOT_DELETE_OFFERS'));
							return false;
						}
					}
				}
			}
		}
		return true;
	}

	public static function OnBeforeCatalogDelete($ID)
	{
		global $APPLICATION;

		$arMsg = array();

		$ID = (int)$ID;
		if (0 >= $ID)
			return true;
		$arCatalog = CCatalogSKU::GetInfoByIBlock($ID);
		if (empty($arCatalog))
			return true;
		if (CCatalogSKU::TYPE_CATALOG != $arCatalog['CATALOG_TYPE'])
		{
			if (CCatalogSKU::TYPE_OFFERS == $arCatalog['CATALOG_TYPE'])
			{
				$arMsg[] = array('id' => 'IBLOCK_ID', 'text' => Loc::getMessage('BT_MOD_CATALOG_ERR_CANNOT_DELETE_SKU_IBLOCK'));
				$obError = new CAdminException($arMsg);
				$APPLICATION->ThrowException($obError);
				return false;
			}
			else
			{
				$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', 'text' => Loc::getMessage('BT_MOD_CATALOG_ERR_CANNOT_DELETE_PRODUCT_IBLOCK'));
				$obError = new CAdminException($arMsg);
				$APPLICATION->ThrowException($obError);
				return false;
			}
		}
		foreach(GetModuleEvents("catalog", "OnBeforeCatalogDelete", true) as $arEvent)
		{
			if (false === ExecuteModuleEventEx($arEvent, array($ID)))
			{
				$strError = Loc::getMessage("BT_MOD_CATALOG_ERR_BEFORE_DEL_TITLE").' '.$arEvent['TO_NAME'];
				if ($ex = $APPLICATION->GetException())
				{
					$strError .= ': '.$ex->GetString();
				}
				$APPLICATION->ThrowException($strError);
				return false;
			}
		}

		return true;
	}

	public static function OnBeforeIBlockPropertyDelete($intPropertyID)
	{
		global $APPLICATION;

		$intPropertyID = (int)$intPropertyID;
		if ($intPropertyID <= 0)
			return true;
		$propertyIterator = Catalog\CatalogIblockTable::getList(array(
			'select' => array('IBLOCK_ID', 'PRODUCT_IBLOCK_ID', 'SKU_PROPERTY_ID'),
			'filter' => array('=SKU_PROPERTY_ID' => $intPropertyID)
		));
		if ($property = $propertyIterator->fetch())
		{
			$APPLICATION->ThrowException(Loc::getMessage(
				'BT_MOD_CATALOG_ERR_CANNOT_DELETE_SKU_PROPERTY',
				array(
					'#SKU_PROPERTY_ID#' => $property['SKU_PROPERTY_ID'],
					'#PRODUCT_IBLOCK_ID#' => $property['PRODUCT_IBLOCK_ID'],
					'#IBLOCK_ID#' => $property['IBLOCK_ID'],
				)
			));
			unset($property, $propertyIterator);
			return false;
		}
		unset($property, $propertyIterator);
		return true;
	}

	public static function OnIBlockModuleUnInstall()
	{
		global $APPLICATION;

		$APPLICATION->ThrowException(Loc::getMessage('BT_MOD_CATALOG_ERR_IBLOCK_REQUIRED'));
		return false;
	}

/*
* @deprecated deprecated since catalog 14.0.0
* @see CCatalogSKU::GetInfoByIBlock()
*/
	public static function GetByIDExt($ID)
	{
		$arResult = CCatalogSKU::GetInfoByIBlock($ID);
		if (!empty($arResult))
		{
			$arResult['OFFERS_IBLOCK_ID'] = 0;
			$arResult['OFFERS_PROPERTY_ID'] = 0;
			$arResult['OFFERS'] = 'N';
			if (CCatalogSKU::TYPE_PRODUCT == $arResult['CATALOG_TYPE'] || CCatalogSKU::TYPE_FULL == $arResult['CATALOG_TYPE'])
			{
				$arResult['OFFERS_IBLOCK_ID'] = $arResult['IBLOCK_ID'];
				$arResult['OFFERS_PROPERTY_ID'] = $arResult['SKU_PROPERTY_ID'];
				$arResult['OFFERS'] = 'Y';
			}
			if (CCatalogSKU::TYPE_PRODUCT != $arResult['CATALOG_TYPE'])
			{
				$arResult['ID'] = $arResult['IBLOCK_ID'];
				$arResult['IBLOCK_TYPE_ID'] = '';
				$arResult['NAME'] = '';
				$arResult['LID'] = '';
				$arIBlock = CIBlock::GetArrayByID($arResult['IBLOCK_ID']);
				if (is_array($arIBlock))
				{
					$arResult['IBLOCK_TYPE_ID'] = $arIBlock['IBLOCK_TYPE_ID'];
					$arResult['NAME'] = $arIBlock['NAME'];
					$arResult['LID'] = $arIBlock['LID'];
				}
			}
		}
		return $arResult;
	}

	public static function UnLinkSKUIBlock($ID)
	{
		global $APPLICATION;

		$arMsg = array();
		$boolResult = true;

		$ID = (int)$ID;
		if (0 >= $ID)
		{
			$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID','text' => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_INVALID'));
			$boolResult = false;
		}

		if ($boolResult)
		{
			$rsCatalog = CCatalog::GetList(
				array(),
				array('PRODUCT_IBLOCK_ID' => $ID),
				false,
				false,
				array('IBLOCK_ID')
			);
			if ($arCatalog = $rsCatalog->Fetch())
			{
				$arCatalog['IBLOCK_ID'] = (int)$arCatalog['IBLOCK_ID'];
				$arFields = array(
					'PRODUCT_IBLOCK_ID' => 0,
					'SKU_PROPERTY_ID' => 0,
				);
				if (!CCatalog::Update($arCatalog['IBLOCK_ID'], $arFields))
					return false;
			}
		}
		if (!$boolResult)
		{
			$obError = new CAdminException($arMsg);
			$APPLICATION->ResetException();
			$APPLICATION->ThrowException($obError);
		}
		else
		{
			CCatalogSKU::ClearCache();
		}
		return $boolResult;
	}

	/**
	 * @deprecated deprecated since catalog 16.0.0
	 * @see CIBlockPropertyTools::createProperty
	 *
	 * @param int $ID				Parent iblock id.
	 * @param int $SKUID			Offer iblock id.
	 * @return int|false
	 */
	public static function LinkSKUIBlock($ID, $SKUID)
	{
		global $APPLICATION;

		$arMsg = array();
		$boolResult = true;

		$propertyId = 0;
		$ID = (int)$ID;
		if (0 >= $ID)
		{
			$arMsg[] = array('id' => 'PRODUCT_IBLOCK_ID', 'text' => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_INVALID'));
			$boolResult = false;
		}
		$SKUID = (int)$SKUID;
		if (0 >= $SKUID)
		{
			$arMsg[] = array('id' => 'OFFERS_IBLOCK_ID', 'text' => Loc::getMessage('BT_MOD_CATALOG_ERR_OFFERS_ID_INVALID'));
			$boolResult = false;
		}
		if ($ID == $SKUID)
		{
			$arMsg[] = array('id' => 'OFFERS_IBLOCK_ID', 'text' => Loc::getMessage('BT_MOD_CATALOG_ERR_PRODUCT_ID_SELF'));
			$boolResult = false;
		}

		if ($boolResult)
		{
			$propertyId = CIBlockPropertyTools::createProperty(
				$SKUID,
				CIBlockPropertyTools::CODE_SKU_LINK,
				array('LINK_IBLOCK_ID' => $ID)
			);
			if (!$propertyId)
			{
				$arMsg = CIBlockPropertyTools::getErrors();
				$boolResult = false;
			}
		}

		if (!$boolResult)
		{
			$obError = new CAdminException($arMsg);
			$APPLICATION->ResetException();
			$APPLICATION->ThrowException($obError);
			return $boolResult;
		}
		else
		{
			return $propertyId;
		}
	}

	/*
	 * @deprecated deprecated since catalog 10.0.3
	 * @internal
	 */
	public static function GetCatalogFieldsList()
	{
		global $DB;
		$arFieldsList = $DB->GetTableFieldsList('b_catalog_iblock');
		$arFieldsList[] = 'CATALOG';
		$arFieldsList[] = 'CATALOG_TYPE';
		$arFieldsList[] = 'OFFERS_IBLOCK_ID';
		$arFieldsList[] = 'OFFERS_PROPERTY_ID';
		$arFieldsList = array_unique($arFieldsList);
		return $arFieldsList;
	}

	public static function IsUserExists()
	{
		global $USER;

		return (isset($USER) && $USER instanceof CUser);
	}

	public static function clearCache()
	{
		self::$arCatalogCache = array();
		self::$catalogVatCache = array();
	}
}