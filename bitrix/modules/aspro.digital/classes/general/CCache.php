<?
// Cache functions
// Tagged cache. After add/delete/update cached objects need to clear tag cache. This events see farther
// (C) Konstantin Chechetkin - ASPRO
if(!class_exists("CCache")){
	class CCache {
		static public $arIBlocks = NULL;
		static public $arIBlocksInfo = NULL;

		function CIBlock_GetList($arOrder = array("SORT" => "ASC", "CACHE" => array("MULTI" => "Y", "GROUP" => array(), "RESULT" => array(), "TAG" => "", "PATH" => "", "TIME" => 36000000)), $arFilter = array(), $bIncCnt = false){
			list($cacheTag, $cachePath, $cacheTime) = self::_InitCacheParams("iblock", __FUNCTION__, $arOrder["CACHE"]);
			$obCache = new CPHPCache();
			$cacheID = __FUNCTION__."_".$cacheTag.md5(serialize(array_merge((array)$arOrder, (array)$arFilter, (array)$bIncCnt)));
			if($obCache->InitCache($cacheTime, $cacheID, $cachePath)){
				$res = $obCache->GetVars();
				$arRes = $res["arRes"];
			}
			else{
				$arRes = array();
				$arResultGroupBy = array("GROUP" => $arOrder["CACHE"]["GROUP"], "MULTI" => $arOrder["CACHE"]["MULTI"], "RESULT" => $arOrder["CACHE"]["RESULT"]);
				unset($arOrder["CACHE"]);
				$dbRes = CIBlock::GetList($arOrder, $arFilter, $bIncCnt);
				while($item = $dbRes->Fetch()){
					if($item['ID']){
						$item['LID'] = array();
						$dbIBlockSites = CIBlock::GetSite($item['ID']);
						while($arIBlockSite = $dbIBlockSites->Fetch()){
							$item['LID'][] = $arIBlockSite['SITE_ID'];
						}
					}
					$arRes[] = $item;
				}

				if($arResultGroupBy["MULTI"] || $arResultGroupBy["GROUP"] || $arResultGroupBy["RESULT"]){
					$arRes = self::GroupArrayBy($arRes, $arResultGroupBy);
				}

				self::_SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID);
			}
			return $arRes;
		}

		function CIBlockElement_GetList($arOrder = array("SORT" => "ASC", "CACHE" => array("MULTI" => "Y", "GROUP" => array(), "RESULT" => array(), "TAG" => "", "PATH" => "", "TIME" => 36000000, "URL_TEMPLATE" => "")), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array()){

			// check filter by IBLOCK_ID === false
			if(array_key_exists("IBLOCK_ID", ($arFilter = (array)$arFilter)) && !$arFilter["IBLOCK_ID"]){
				return false;
			}

			list($cacheTag, $cachePath, $cacheTime) = self::_InitCacheParams("iblock", __FUNCTION__, $arOrder["CACHE"]);
			if(is_array($arSelectFields) && $arSelectFields){
				$arSelectFields[] = "ID";
			}
			$obCache = new CPHPCache();
			$cacheID = __FUNCTION__."_".$cacheTag.md5(serialize(array_merge((array)$arOrder, $arFilter, (array)$arGroupBy, (array)$arNavStartParams, (array)$arSelectFields)));
			if($obCache->InitCache($cacheTime, $cacheID, $cachePath)){
				$res = $obCache->GetVars();
				$arRes = $res["arRes"];
			}
			else{
				$arRes = array();
				$arResultGroupBy = array("GROUP" => $arOrder["CACHE"]["GROUP"], "MULTI" => $arOrder["CACHE"]["MULTI"], "RESULT" => $arOrder["CACHE"]["RESULT"]);
				$urlTemplate = $arOrder["CACHE"]["URL_TEMPLATE"];
				unset($arOrder["CACHE"]);

				$dbRes = CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelectFields);
				if($arGroupBy === array()){
					// only count
					$arRes = $dbRes;
				}
				else{
					if(strlen($urlTemplate)){
						$dbRes->SetUrlTemplates($urlTemplate, '');
					}

					$arResultIDsIndexes = array();
					$bGetSectionIDsArray = (in_array("IBLOCK_SECTION_ID", $arSelectFields) || !$arSelectFields);
					if($bGetDetailPageUrlsArray = (in_array("DETAIL_PAGE_URL", $arSelectFields) || !$arSelectFields)){
						if($arSelectFields){
							if(!in_array("IBLOCK_ID", $arSelectFields)){
								$arSelectFields[] = "IBLOCK_ID";
							}
							if(!in_array("IBLOCK_SECTION_ID", $arSelectFields)){
								$arSelectFields[] = "IBLOCK_SECTION_ID";
							}
							if(!in_array("ID", $arSelectFields)){
								$arSelectFields[] = "ID";
							}
							if(!in_array("CANONICAL_PAGE_URL", $arSelectFields)){
								$arSelectFields[] = "CANONICAL_PAGE_URL";
							}
						}
						$bGetSectionIDsArray = true;
					}
					// fields and properties
					$arRes = self::_GetFieldsAndProps($dbRes, $arSelectFields, $bGetSectionIDsArray);
					if($bGetDetailPageUrlsArray){
						$arBySectionID = $arNewDetailPageUrls = $arCanonicalPageUrls = $arByIBlock = array();
						$FilterIblockID = $arFilter["IBLOCK_ID"];
						$FilterSectionID = $arFilter["SECTION_ID"];
						foreach($arRes as $arItem){
							if($IBLOCK_ID = ($arItem["IBLOCK_ID"] ? $arItem["IBLOCK_ID"] : $FilterIblockID)){
								if($arSectionIDs = ($arItem["IBLOCK_SECTION_ID"] ? $arItem["IBLOCK_SECTION_ID"] : $FilterSectionID)){
									if(!is_array($arSectionIDs)){
										$arSectionIDs = array($arSectionIDs);
									}
									foreach($arSectionIDs as $SID){
										$arByIBlock[$IBLOCK_ID][$SID][] = $arItem["ID"];
									}
								}
							}
							else{
								$arNewDetailPageUrls[$arItem["ID"]] = array($arItem["DETAIL_PAGE_URL"]);
								if(strlen($arItem["CANONICAL_PAGE_URL"])){
									$arCanonicalPageUrls[$arItem["ID"]] = $arItem["CANONICAL_PAGE_URL"];
								}
							}
						}

						foreach($arByIBlock as $IBLOCK_ID => $arIB){
							$arSectionIDs = $arSections = array();
							foreach($arIB as $SECTION_ID => $arIDs){
								$arSectionIDs[] = $SECTION_ID;
							}
							if($arSectionIDs){
								$arSections = CCache::CIBlockSection_GetList(array("CACHE" => array("TAG" => CCache::GetIBlockCacheTag($IBLOCK_ID), "MULTI" => "N", "GROUP" => array("ID"))), array("ID" => $arSectionIDs), false, array("ID", "CODE", "EXTERNAL_ID", "IBLOCK_ID"));
							}
							foreach($arIB as $SECTION_ID => $arIDs){
								if($arIDs){
									$rsElements = CIBlockElement::GetList(array(), array("ID" => $arIDs), false, false, array("ID", "DETAIL_PAGE_URL", "CANONICAL_PAGE_URL"));
									$rsElements->SetUrlTemplates(CCache::$arIBlocksInfo[$IBLOCK_ID]["DETAIL_PAGE_URL"]);
									$rsElements->SetSectionContext($arSections[$SECTION_ID]);
									while($arElement = $rsElements->GetNext()){
										$arNewDetailPageUrls[$arElement["ID"]][$SECTION_ID] = $arElement["DETAIL_PAGE_URL"];
										if(strlen($arElement["CANONICAL_PAGE_URL"])){
											$arCanonicalPageUrls[$arElement["ID"]] = $arElement["CANONICAL_PAGE_URL"];
										}
									}
								}
							}
						}

						foreach($arRes as $i => $arItem){
							if(count($arNewDetailPageUrls[$arItem["ID"]]) > 1){
								if(isset($arCanonicalPageUrls[$arItem["ID"]]) && strlen($arCanonicalPageUrls[$arItem["ID"]])){
									$arRes[$i]["DETAIL_PAGE_URL"] = $arCanonicalPageUrls[$arItem["ID"]];
								}
								else{
									$arRes[$i]["DETAIL_PAGE_URL"] = $arNewDetailPageUrls[$arItem["ID"]];
								}
							}
							unset($arRes[$i]["~DETAIL_PAGE_URL"]);
						}

					}

					if($arResultGroupBy["MULTI"] || $arResultGroupBy["GROUP"] || $arResultGroupBy["RESULT"]){
						$arRes = self::GroupArrayBy($arRes, $arResultGroupBy);
					}
				}

				self::_SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID);
			}
			return $arRes;
		}

		function CIBlockSection_GetList($arOrder = array("SORT" => "ASC", "CACHE" => array("MULTI" => "Y", "GROUP" => array(), "RESULT" => array(), "TAG" => "", "PATH" => "", "TIME" => 36000000, "URL_TEMPLATE" => "")), $arFilter = array(), $bIncCnt = false, $arSelectFields = array(), $arNavStartParams = false){

			// check filter by IBLOCK_ID === false
			if(array_key_exists("IBLOCK_ID", ($arFilter = (array)$arFilter)) && !$arFilter["IBLOCK_ID"]){
				return false;
			}

			list($cacheTag, $cachePath, $cacheTime) = self::_InitCacheParams("iblock", __FUNCTION__, $arOrder["CACHE"]);
			if(is_array($arSelectFields) && $arSelectFields){
				$arSelectFields[] = "ID";
			}
			$obCache = new CPHPCache();
			$cacheID = __FUNCTION__."_".$cacheTag.md5(serialize(array_merge((array)$arOrder, (array)$arFilter, (array)$bIncCnt, (array)$arNavStartParams, (array)$arSelectFields)));
			if($obCache->InitCache($cacheTime, $cacheID, $cachePath)){
				$res = $obCache->GetVars();
				$arRes = $res["arRes"];
			}
			else{
				$arRes = array();
				$arResultGroupBy = array("GROUP" => $arOrder["CACHE"]["GROUP"], "MULTI" => $arOrder["CACHE"]["MULTI"], "RESULT" => $arOrder["CACHE"]["RESULT"]);
				$urlTemplate = $arOrder["CACHE"]["URL_TEMPLATE"];
				unset($arOrder["CACHE"]);

				$dbRes = CIBlockSection::GetList($arOrder, $arFilter, $bIncCnt, $arSelectFields, $arNavStartParams);

				if(strlen($urlTemplate)){
					$dbRes->SetUrlTemplates('', $urlTemplate);
				}

				// fields and properties
				$arRes = self::_GetFieldsAndProps($dbRes, $arSelectFields);

				if($arResultGroupBy["MULTI"] || $arResultGroupBy["GROUP"] || $arResultGroupBy["RESULT"]){
					$arRes = self::GroupArrayBy($arRes, $arResultGroupBy);
				}

				self::_SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID);
			}
			return $arRes;
		}

		function CSaleBasket_GetList($arOrder = array("SORT" => "ASC"), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array(), $cacheTag = "", $cacheTime = 36000000, $cachePath = ""){
			CModule::IncludeModule('sale');
			if(!strlen($cacheTag)){
				$cacheTag = "_notag";
			}
			if(!strlen($cachePath)){
				$cachePath = "/ccache/sale/CSaleBasket_GetList/".$cacheTag."/";
			}
			$obCache = new CPHPCache();
			$cacheID = 'CSaleBasket_GetList_'.$cacheTag.md5(serialize(array_merge((array)$arOrder, (array)$arFilter, (array)$arGroupBy, (array)$arNavStartParams, (array)$arSelectFields)));
			if($obCache->InitCache($cacheTime, $cacheID, $cachePath)){
				$res = $obCache->GetVars();
				$arRes = $res["arRes"];
			}
			else{
				$arRes = array();
				$arResultGroupBy = array("GROUP" => $arGroupBy["GROUP"], "MULTI" => $arGroupBy["MULTI"], "RESULT" => $arSelectFields["RESULT"]);
				$arGroupBy = (isset($arGroupBy["BX"]) ? $arGroupBy["BX"] : $arGroupBy);
				$dbRes = CSaleBasket::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelectFields);
				if(in_array("DETAIL_PAGE_URL", $arSelectFields) === false){
					while($item = $dbRes->Fetch()){
						$arRes[] = $item;
					}
				}
				else{
					while($item = $dbRes->GetNext()){
						$arRes[] = $item;
					}
				}

				if($arResultGroupBy["MULTI"] || $arResultGroupBy["GROUP"] || $arResultGroupBy["RESULT"]){
					$arRes = self::GroupArrayBy($arRes, $arResultGroupBy);
				}

				self::_SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID);
			}
			return $arRes;
		}

		function CForumMessage_GetListEx($arOrder = array("SORT" => "ASC"), $arFilter = array(), $arGroupBy = false, $iNum = 0, $arSelectFields = array(), $cacheTag = "", $cacheTime = 36000000, $cachePath = ""){
			CModule::IncludeModule('forum');
			if(!strlen($cacheTag)){
				$cacheTag = "_notag";
			}
			if(!strlen($cachePath)){
				$cachePath = "/ccache/forum/CForumMessage_GetListEx/".$cacheTag."/";
			}
			$obCache = new CPHPCache();
			$cacheID = 'CForumMessage_GetListEx_'.$cacheTag.md5(serialize(array_merge((array)$arOrder, (array)$arFilter, (array)$arGroupBy, (array)$iNum, (array)$arSelectFields)));
			if($obCache->InitCache($cacheTime, $cacheID, $cachePath)){
				$res = $obCache->GetVars();
				$arRes = $res["arRes"];
			}
			else{
				$arRes = array();
				$arResultGroupBy = array("GROUP" => $arGroupBy["GROUP"], "MULTI" => $arGroupBy["MULTI"], "RESULT" => $arSelectFields["RESULT"]);
				$bCount = (isset($arGroupBy["BX"]) ? $arGroupBy["BX"] : $arGroupBy);
				$dbRes = CForumMessage::GetListEx($arOrder, $arFilter, $bCount, $iNum, $arSelectFields);
				if($bCount){
					$arRes = $dbRes;
				}
				else{
					while($item = $dbRes->Fetch()){
						$arRes[] = $item;
					}

					if($arResultGroupBy["MULTI"] || $arResultGroupBy["GROUP"] || $arResultGroupBy["RESULT"]){
						$arRes = self::GroupArrayBy($arRes, $arResultGroupBy);
					}
				}

				self::_SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID);
			}
			return $arRes;
		}

		private function _MakeResultTreeArray($arParams, &$arItem, &$arItemResval, &$to){
			if($arParams["GROUP"]){
				$newto = $to;
				$FieldID = array_shift($arParams["GROUP"]);
				$arFieldValue = (is_array($arItem[$FieldID]) ? $arItem[$FieldID] : array($arItem[$FieldID]));

				foreach($arFieldValue as $FieldValue){
					if(!isset($to[$FieldValue])){
						$to[$FieldValue] = false;
					}
					$newto = &$to[$FieldValue];
					self::_MakeResultTreeArray($arParams, $arItem, $arItemResval, $newto);
				}
			}
			else{
				if($arParams["MULTI"] == "Y"){
					$to[] = $arItemResval;
				}
				elseif($arParams["MULTI"] == "YM"){
					if($to){
						$to = array_merge((array)$to, (array)$arItemResval);
					}
					else{
						$to = $arItemResval;
					}
				}
				else{
					$to = $arItemResval;
				}
			}
		}

		function GroupArrayBy($arItems, $arParams){
			$arRes = array();
			$resultIDsCount = count($arParams["RESULT"]);
			$arParams["RESULT"] = array_diff((array)$arParams["RESULT"], array(null));
			$arParams["GROUP"] = array_diff((array)$arParams["GROUP"], array(null));
			foreach($arItems as $arItem){
				$val = false;
				if($resultIDsCount){
					if($resultIDsCount > 1){
						foreach($arParams["RESULT"] as $ID){
							$val[$ID] = $arItem[$ID];
						}
					}
					else{
						$val = $arItem[current($arParams["RESULT"])];
					}
				}
				else{
					$val = $arItem;
				}
				self::_MakeResultTreeArray($arParams, $arItem, $val, $arRes);
			}
			return $arRes;
		}

		private function _InitCacheParams($moduleName, $functionName, $arCache){
			CModule::IncludeModule($moduleName);
			$cacheTag = $arCache["TAG"];
			$cachePath = $arCache["PATH"];
			$cacheTime = ($arCache["TIME"] > 0 ? $arCache["TIME"] : 36000000);
			if(!strlen($cacheTag)){
				$cacheTag = "_notag";
			}
			if(!strlen($cachePath)){
				$cachePath = "/CCache/".$moduleName."/".$functionName."/".$cacheTag."/";
			}
			return array($cacheTag, $cachePath, $cacheTime);
		}

		private function _GetElementSectionsArray($ID){
			$arSections = array();
			$resGroups = CIBlockElement::GetElementGroups($ID, true, array("ID"));
			while($arGroup = $resGroups->Fetch()){
				$arSections[] = $arGroup["ID"];
			}
			return (!$arSections ? false : (count($arSections) == 1 ? current($arSections) : $arSections));
		}

		private function _GetFieldsAndProps($dbRes, $arSelectFields, $bIsIblockElement = false){
			$arRes = $arResultIDsIndexes = array();
			if($arSelectFields && (in_array("DETAIL_PAGE_URL", $arSelectFields) === false && in_array("SECTION_PAGE_URL", $arSelectFields) === false)){
				$func = "Fetch";
			}
			else{
				$func = "GetNext";
			}
			while($item = $dbRes->$func()){
				if(($existKey = ($arResultIDsIndexes[$item["ID"]] ? $arResultIDsIndexes[$item["ID"]] : ($arResultIDsIndexes[$item["ID"]] !== null ? false : null))) !== null){
					$existItem = &$arRes[$existKey];
					if($bIsIblockElement){
						unset($item["IBLOCK_SECTION_ID"]);
						unset($item["~IBLOCK_SECTION_ID"]);
					}
					foreach($item as $key => $val){
						if($key == "ID") {
							continue;
						}
						if(isset($existItem[$key])){
							if(is_array($existItem[$key])){
								if(!in_array($val, $existItem[$key])){
									$existItem[$key][] = $val;
								}
							}
							else{
								if($existItem[$key] != $val){
									$existItem[$key] = array($existItem[$key], $val);
								}
							}
						}
						else{
							$existItem[$key] = $val;
						}
					}
				}
				else{
					if($bIsIblockElement){
						$item["IBLOCK_SECTION_ID"] = self::_GetElementSectionsArray($item["ID"]);
						unset($item["~IBLOCK_SECTION_ID"]);
					}
					$arResultIDsIndexes[$item["ID"]] = count($arRes);
					$arRes[] = $item;
				}
			}

			return $arRes;
		}

		private function _SaveDataCache($obCache, $arRes, $cacheTag, $cachePath, $cacheTime, $cacheID){
			if($cacheTime > 0){
				$obCache->StartDataCache($cacheTime, $cacheID, $cachePath);

				if(strlen($cacheTag)){
					global $CACHE_MANAGER;
					$CACHE_MANAGER->StartTagCache($cachePath);
					$CACHE_MANAGER->RegisterTag($cacheTag);
					$CACHE_MANAGER->EndTagCache();
				}

				$obCache->EndDataCache(array("arRes" => $arRes));
			}
		}

		function GetIBlockCacheTag($IBLOCK_ID){
			if(!$IBLOCK_ID){
				return false;
			}
			else{
				return @CCache::$arIBlocksInfo[$IBLOCK_ID]["CODE"].$IBLOCK_ID;
			}
		}

		function ClearTagIBlock($arFields){
			global $CACHE_MANAGER;
			$CACHE_MANAGER->ClearByTag("iblocks");
		}

		function ClearTagIBlockBeforeDelete($ID){
			global $CACHE_MANAGER;
			$CACHE_MANAGER->ClearByTag("iblocks");
		}

		function ClearTagIBlockElement($arFields){
			global $CACHE_MANAGER;
			if($arFields["IBLOCK_ID"]){
				$CACHE_MANAGER->ClearByTag(CCache::GetIBlockCacheTag($arFields["IBLOCK_ID"]));
			}
		}

		function ClearTagIBlockSection($arFields){
			global $CACHE_MANAGER;
			if($arFields["IBLOCK_ID"]){
				$CACHE_MANAGER->ClearByTag(CCache::GetIBlockCacheTag($arFields["IBLOCK_ID"]));
			}
		}

		function ClearTagIBlockSectionBeforeDelete($ID){
			global $CACHE_MANAGER;
			if($ID > 0){
				if($IBLOCK_ID = CCache::CIBlockSection_GetList(array("CACHE" => array("MULTI" => "N")), array("ID" => $ID), false, array("IBLOCK_ID"), true)){
					$CACHE_MANAGER->ClearByTag(CCache::GetIBlockCacheTag($IBLOCK_ID));
				}
			}
		}
	}

	
	// initialize CCache::$arIBlocks array
	if(CCache::$arIBlocks === NULL){
		$arIBlocksTmp = CCache::CIBlock_GetList(array("CACHE" => array("TAG" => "iblocks")), array("ACTIVE" => "Y", "CHECK_PERMISSIONS" => "N"));
		CCache::$arIBlocks = CCache::GroupArrayBy($arIBlocksTmp, array("GROUP" => array("LID", "IBLOCK_TYPE_ID", "CODE"), "MULTI" => "Y", "RESULT" => array("ID")));
		CCache::$arIBlocksInfo = CCache::GroupArrayBy($arIBlocksTmp, array("GROUP" => array("ID")));
	}
}
?>