<?
foreach($arResult['ITEMS'] as $key => $arItem){
	if($SID = $arItem['IBLOCK_SECTION_ID']){
		$arSectionsIDs[] = $SID;
	}
	$arResult['ITEMS'][$key]['DETAIL_PAGE_URL'] = CDigital::FormatNewsUrl($arItem);
	
	if(strlen($arItem['DISPLAY_PROPERTIES']['REDIRECT']['VALUE']))
		unset($arResult['ITEMS'][$key]['DISPLAY_PROPERTIES']['REDIRECT']);
	
	CDigital::getFieldImageData($arResult['ITEMS'][$key], array('PREVIEW_PICTURE'));
}

if($arSectionsIDs){
	$arResult['SECTIONS'] = CCache::CIBLockSection_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CCache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'GROUP' => array('ID'), 'MULTI' => 'N')), array('ID' => $arSectionsIDs));
}
?>