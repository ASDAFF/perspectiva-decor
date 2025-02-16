<?

use Bitrix\Main\UI\Filter\Type;
use Bitrix\Main\UI\Filter\Field;
use Bitrix\Main\UI\Filter\DateType;
use Bitrix\Main\UI\Filter\NumberType;
use Bitrix\Main\Localization\Loc;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
	die();
}

Loc::loadMessages(__FILE__);


class CMainUiFilter extends CBitrixComponent
{
	protected $defaultViewSort = 500;
	protected $options;
	protected $jsFolder = "/js/";
	protected $blocksFolder = "/blocks/";
	protected $cssFolder = "/css/";
	protected $commonOptions;


	protected function prepareResult()
	{
		$this->arResult["FILTER_ID"] = $this->arParams["FILTER_ID"];
		$this->arResult["GRID_ID"] = $this->arParams["GRID_ID"];
		$this->arResult["FIELDS"] = $this->prepareFields();
		$this->arResult["PRESETS"] = $this->preparePresets();
		$this->arResult["TARGET_VIEW_ID"] = $this->getViewId();
		$this->arResult["TARGET_VIEW_SORT"] = $this->getViewSort();
		$this->arResult["SETTINGS_URL"] = $this->prepareSettingsUrl();
		$this->arResult["FILTER_ROWS"] = $this->prepareFilterRows();
		$this->arResult["CURRENT_PRESET"] = $this->prepareDefaultPreset();
		$this->arResult["ENABLE_LABEL"] = $this->prepareEnableLabel();
		$this->arResult["ENABLE_LIVE_SEARCH"] = $this->prepareEnableLiveSearch();
		$this->arResult["DISABLE_SEARCH"] = $this->prepareDisableSearch();
		$this->arResult["MAIN_UI_FILTER__AND"] = Loc::getMessage('MAIN_UI_FILTER__AND');
		$this->arResult["MAIN_UI_FILTER__MORE"] = Loc::getMessage('MAIN_UI_FILTER__MORE');
		$this->arResult["MAIN_UI_FILTER__BEFORE"] = Loc::getMessage("MAIN_UI_FILTER__BEFORE");
		$this->arResult["MAIN_UI_FILTER__AFTER"] = Loc::getMessage("MAIN_UI_FILTER__AFTER");
		$this->arResult["MAIN_UI_FILTER__NUMBER_MORE"] = Loc::getMessage("MAIN_UI_FILTER__NUMBER_MORE");
		$this->arResult["MAIN_UI_FILTER__NUMBER_LESS"] = Loc::getMessage("MAIN_UI_FILTER__NUMBER_LESS");
		$this->arResult["MAIN_UI_FILTER__PLACEHOLDER_DEFAULT"] = Loc::getMessage("MAIN_UI_FILTER__PLACEHOLDER_DEFAULT");
		$this->arResult["MAIN_UI_FILTER__PLACEHOLDER_WITH_FILTER"] = Loc::getMessage("MAIN_UI_FILTER__PLACEHOLDER_WITH_FILTER");
		$this->arResult["MAIN_UI_FILTER__PLACEHOLDER"] = Loc::getMessage("MAIN_UI_FILTER__PLACEHOLDER");
		$this->arResult["MAIN_UI_FILTER__QUARTER"] = Loc::getMessage("MAIN_UI_FILTER__QUARTER");
		$this->arResult["MAIN_UI_FILTER__IS_SET_AS_DEFAULT_PRESET"] = Loc::getMessage("MAIN_UI_FILTER__IS_SET_AS_DEFAULT_PRESET");
		$this->arResult["MAIN_UI_FILTER__SET_AS_DEFAULT_PRESET"] = Loc::getMessage("MAIN_UI_FILTER__SET_AS_DEFAULT_PRESET");
		$this->arResult["MAIN_UI_FILTER__EDIT_PRESET_TITLE"] = Loc::getMessage("MAIN_UI_FILTER__EDIT_PRESET_TITLE");
		$this->arResult["MAIN_UI_FILTER__REMOVE_PRESET"] = Loc::getMessage("MAIN_UI_FILTER__REMOVE_PRESET");
		$this->arResult["MAIN_UI_FILTER__DRAG_TITLE"] = Loc::getMessage("MAIN_UI_FILTER__DRAG_TITLE");
		$this->arResult["MAIN_UI_FILTER__DRAG_FIELD_TITLE"] = Loc::getMessage("MAIN_UI_FILTER__DRAG_FIELD_TITLE");
		$this->arResult["MAIN_UI_FILTER__REMOVE_FIELD"] = Loc::getMessage("MAIN_UI_FILTER__REMOVE_FIELD");
		$this->arResult["MAIN_UI_FILTER__CONFIRM_MESSAGE_FOR_ALL"] = Loc::getMessage("MAIN_UI_FILTER__CONFIRM_MESSAGE_FOR_ALL");
		$this->arResult["MAIN_UI_FILTER__CONFIRM_APPLY_FOR_ALL"] = Loc::getMessage("MAIN_UI_FILTER__CONFIRM_APPLY_FOR_ALL");
		$this->arResult["CLEAR_GET"] = $this->prepareClearGet();
		$this->arResult["VALUE_REQUIRED_MODE"] = $this->prepareValueRequiredMode();
	}

	protected function prepareValueRequiredMode()
	{
		return ($this->arParams["VALUE_REQUIRED_MODE"] == true);
	}

	protected function prepareClearGet()
	{
		$apply = $this->request->get("apply_filter");
		return !empty($apply);
	}

	protected function prepareDisableSearch()
	{
		$result = false;

		if (isset($this->arParams["DISABLE_SEARCH"]) && $this->arParams["DISABLE_SEARCH"])
		{
			$result = true;
		}

		return $result;
	}

	protected function prepareEnableLiveSearch()
	{
		$this->arResult["ENABLE_LIVE_SEARCH"] = false;

		if (isset($this->arParams["ENABLE_LIVE_SEARCH"]) && is_bool($this->arParams["ENABLE_LIVE_SEARCH"]))
		{
			$this->arResult["ENABLE_LIVE_SEARCH"] = $this->arParams["ENABLE_LIVE_SEARCH"];
		}

		return $this->arResult["ENABLE_LIVE_SEARCH"];
	}

	protected function prepareEnableLabel()
	{
		$this->arResult["ENABLE_LABEL"] = false;

		if (is_bool($this->arParams["ENABLE_LABEL"]) && $this->arParams["ENABLE_LABEL"] === true)
		{
			$this->arResult["ENABLE_LABEL"] = true;
		}

		return $this->arResult["ENABLE_LABEL"];
	}

	protected function getUserOptions()
	{
		if (!($this->options instanceof \Bitrix\Main\UI\Filter\Options))
		{
			$this->options = new \Bitrix\Main\UI\Filter\Options(
				$this->arParams["FILTER_ID"],
				$this->arParams["FILTER_PRESETS"]
			);
		}

		return $this->options;
	}

	protected function prepareParams()
	{
		$options = $this->getUserOptions();
		$presets = $this->arParams["FILTER_PRESETS"];

		foreach ($presets as $key => $preset)
		{
			if ($options->isDeletedPreset($key))
			{
				unset($this->arParams["FILTER_PRESETS"][$key]);
			}
		}

		$this->arParams["FILTER_ROWS"] = $this->prepareFilterRowsParam();
	}

	protected function prepareFilterRowsParam()
	{
		if (!isset($this->arParams["FILTER_ROWS"]) || !is_array($this->arParams["FILTER_ROWS"]))
		{
			$this->arParams["FILTER_ROWS"] = array();

			if (isset($this->arParams["FILTER"]) &&
				!empty($this->arParams["FILTER"]) &&
				is_array($this->arParams["FILTER"]))
			{
				foreach ($this->arParams["FILTER"] as $key => $field)
				{
					if ($field["default"])
					{
						$this->arParams["FILTER_ROWS"][$field["id"]] = true;
					}
				}
			}
		}

		return $this->arParams["FILTER_ROWS"];
	}



	protected static function prepareSelectValue(Array $items = array(), $value = "")
	{
		$result = array();

		foreach ($items as $key => $item)
		{
			if ($item["VALUE"] == $value)
			{
				$result = $item;
				continue;
			}
		}

		return $result;
	}

	protected static function prepareMultiselectValue(Array $items = array(), Array $value = array())
	{
		$result = array();
		$values = array_values($value);

		foreach ($items as $key => $item)
		{
			if (in_array($item["VALUE"], $values))
			{
				$result[] = $item;
			}
		}

		return $result;
	}

	protected static function prepareValue(Array $field, Array $presetFields = array(), $prefix)
	{
		$fieldValuesKeys = array_keys($field["VALUES"]);
		$fieldName = strpos($field["NAME"], $prefix) !== false ? str_replace($prefix, "", $field["NAME"]) : $field["NAME"];
		$result = array();

		foreach ($fieldValuesKeys as $key => $keyName)
		{
			$currentFieldName = $fieldName.$keyName;
			$result[$keyName] = "";

			if (array_key_exists($currentFieldName, $presetFields))
			{
				$result[$keyName] = $presetFields[$currentFieldName];
			}
		}

		return $result;
	}

	protected static function prepareSubtype(Array $field, Array $presetFields = array(), $prefix)
	{
		$subTypes = $field["SUB_TYPES"];
		$dateselName = strpos($field["NAME"], $prefix) === false ? $field["NAME"].$prefix : $field["NAME"];
		$result = $subTypes[0];

		if (array_key_exists($dateselName, $presetFields))
		{
			foreach ($subTypes as $key => $subType)
			{
				if ($subType["VALUE"] === $presetFields[$dateselName])
				{
					$result = $subType;
				}
			}
		}

		return $result;
	}

	protected static function prepareCustomEntityValue(Array $field, Array $presetFields = array())
	{
		$fieldName = $field["NAME"];
		$fieldNameLabel = $fieldName."_label";
		$fieldNameLabelAlias = $fieldName."_name";
		$fieldNameValue = $fieldName."_value";
		$result = array(
			"_label" => "",
			"_value" => ""
		);

		if (array_key_exists($fieldName, $presetFields))
		{
			$result["_value"] = $presetFields[$fieldName];

		}

		if (empty($result["_value"]) && array_key_exists($fieldNameValue, $presetFields))
		{
			$result["_value"] = $presetFields[$fieldNameValue];

		}

		if (array_key_exists($fieldNameLabel, $presetFields))
		{
			$result["_label"] = $presetFields[$fieldNameLabel];
		}

		if (empty($result["_label"]) && array_key_exists($fieldNameLabelAlias, $presetFields))
		{
			$result["_label"] = $presetFields[$fieldNameLabelAlias];
		}

		if (!empty($result["_value"]) && empty($result["_label"]))
		{
			$result["_label"] = "#".$result["_value"];
		}

		return $result;
	}

	protected static function prepareCustomValue(Array $field, Array $presetFields = array())
	{
		return array_key_exists($field["NAME"], $presetFields) ? $presetFields[$field["NAME"]] : "";
	}

	protected function preparePresetFields(Array $presetRows = array(), Array $presetFields = array())
	{
		$result = array();

		foreach ($presetRows as $rowKey => $rowName)
		{
			$field = $this->getField($rowName);

			if (empty($field))
			{
				continue;
			}

			$value = array_key_exists($rowName, $presetFields) ? $presetFields[$rowName] : "";

			switch ($field["TYPE"])
			{
				case Type::SELECT :
				{
					if (!empty($value) && is_array($value))
					{
						$values = array_values($value);
						$value = $values[0];
					}

					$field["VALUE"] = self::prepareSelectValue($field["ITEMS"], $value);
					break;
				}

				case Type::MULTI_SELECT :
				{
					$value = is_array($value) ? $value : array();
					$field["VALUE"] = self::prepareMultiselectValue($field["ITEMS"], $value);
					break;
				}

				case Type::DATE :
				{
					$field["SUB_TYPE"] = self::prepareSubtype($field, $presetFields, "_datesel");
					$field["VALUES"] = self::prepareValue($field, $presetFields, "_datesel");
					break;
				}

				case Type::NUMBER :
				{
					$field["SUB_TYPE"] = self::prepareSubtype($field, $presetFields, "_numsel");
					$field["VALUES"] = self::prepareValue($field, $presetFields, "_numsel");
					break;
				}

				case Type::CUSTOM_ENTITY :
				{
					$field["VALUES"] = self::prepareCustomEntityValue($field, $presetFields);
					break;
				}

				case Type::CUSTOM :
				{
					$field["_VALUE"] = self::prepareCustomValue($field, $presetFields);
					break;
				}

				case Type::STRING :
				{
					$field["VALUE"] = $value;
					break;
				}
			}

			$result[] = $field;
		}

		return $result;
	}

	protected function applyOptions()
	{
		$options = $this->getUserOptions();
		$arOptions = $options->getOptions();
		$optionsPresets = $arOptions["filters"];
		$defaultPresets = $this->preparePresets();
		$arFilter = $options->getFilter($this->arParams["FILTER"]);

		if (!empty($optionsPresets) && is_array($optionsPresets))
		{
			foreach ($optionsPresets as $presetId => $presetFields)
			{
				$rows = array();
				if (isset($presetFields["filter_rows"]))
				{
					$rows = explode(",", $presetFields["filter_rows"]);
				}
				elseif(isset($presetFields["fields"]) && is_array($presetFields["fields"]))
				{
					$rows = array_keys($presetFields["fields"]);
				}

				$fields = isset($presetFields["fields"]) && is_array($presetFields["fields"])
					? $presetFields["fields"] : array();

				$preset = array(
					"ID" => $presetId,
					"SORT" => $presetFields["sort"],
					"TITLE" => $presetFields["name"],
					"FIELDS" => $this->preparePresetFields($rows, $fields),
					"IS_PINNED" => false
				);

				if ($arOptions["default"] === $presetId)
				{
					$preset["IS_PINNED"] = true;
				}

				if ($preset["ID"] === "default_filter")
				{
					$preset["FIELDS_COUNT"] = $this->prepareFieldsCount();
				}

				if ($preset["ID"] === "tmp_filter")
				{
					$preset["FIELDS_COUNT"] = $this->prepareFieldsCount();
				}

				$isReplace = array_key_exists($presetId, $this->arParams["FILTER_PRESETS"]);
				if ($isReplace || $preset["ID"] === "default_filter")
				{
					foreach ($defaultPresets as $defKey => $defaultPreset)
					{
						if ($defaultPreset["ID"] === $preset["ID"])
						{
							if (!isset($presetFields["fields"]) && !isset($presetFields["filter_rows"]))
							{
								$preset["FIELDS"] = $this->arResult["PRESETS"][$defKey]["FIELDS"];
							}

							$this->arResult["PRESETS"][$defKey] = $preset;
						}
					}
				}
				else
				{
					$this->arResult["PRESETS"][] = $preset;
				}
			}

			if (isset($arFilter["PRESET_ID"]))
			{
				foreach ($this->arResult["PRESETS"] as $key => $preset)
				{
					if ($arFilter["PRESET_ID"] === $preset["ID"])
					{
						$this->arResult["CURRENT_PRESET"] = $preset;
						$this->arResult["CURRENT_PRESET"]["FIND"] = $arFilter["FIND"];
					}
				}
			}
		}

		\Bitrix\Main\Type\Collection::sortByColumn(
			$this->arResult["PRESETS"],
			array("SORT" => array(SORT_NUMERIC, SORT_ASC)),
			'',
			1000
		);
	}

	protected function prepareDefaultPreset()
	{
		if (!is_array($this->arResult["CURRENT_PRESET"]))
		{
			$this->arResult["CURRENT_PRESET"] = array(
				"ID" => "default_filter",
				"TITLE" => Loc::getMessage("MAIN_UI_FILTER__DEFAULT_FILTER_TITLE"),
				"FIELDS" => $this->prepareFilterRows(),
				"FIELDS_COUNT" => $this->prepareFieldsCount()
			);
		}

		return $this->arResult["CURRENT_PRESET"];
	}

	protected function prepareFieldsCount()
	{
		$options = $this->getUserOptions();
		$filter = $options->getFilter($this->arParams["FILTER"]);
		$arOptions = $options->getOptions();
		$count = 0;

		if (!empty($filter) && array_key_exists($filter["PRESET_ID"], $arOptions["filters"]))
		{
			$preset = $arOptions["filters"][$filter["PRESET_ID"]];
			$fields = $preset["fields"];
			$rows = explode(",", $preset["filter_rows"]);

			foreach ($rows as $key => $row)
			{
				if (array_key_exists($row, $fields) && !empty($fields[$row]))
				{
					$count++;
				}
				else
				{
					$dataRow = $row."_datesel";
					$numRow = $row."_numsel";
					$from = $row."_from";
					$to = $row."_to";
					$days = $row."_days";

					if ((array_key_exists($dataRow, $fields) || array_key_exists($numRow, $fields)) && (
							(array_key_exists($from, $fields) && !empty($fields[$from])) ||
							(array_key_exists($to, $fields) && !empty($fields[$to])) ||
							(array_key_exists($days, $fields) && !empty($fields[$days]))
						)
					)
					{
						$count++;
					}
				}
			}
		}

		return $count;
	}

	protected function prepareFilterRows()
	{
		if (!is_array($this->arResult["FILTER_ROWS"]))
		{
			$this->arResult["FILTER_ROWS"] = array();

			if (isset($this->arParams["FILTER_ROWS"]) &&
				!empty($this->arParams["FILTER_ROWS"]) &&
				is_array($this->arParams["FILTER_ROWS"]))
			{
				foreach ($this->arParams["FILTER_ROWS"] as $rowId => $isEnabled)
				{
					if ($isEnabled)
					{
						$field = $this->getField($rowId);
						$this->arResult["FILTER_ROWS"][] = $field;
					}
				}
			}
		}

		return $this->arResult["FILTER_ROWS"];
	}

	protected function getViewId()
	{
		$viewId = "";

		if (isset($this->arParams["RENDER_FILTER_INTO_VIEW"]) &&
			!empty($this->arParams["RENDER_FILTER_INTO_VIEW"]) &&
			is_string($this->arParams["RENDER_FILTER_INTO_VIEW"]))
		{
			$viewId = $this->arParams["RENDER_FILTER_INTO_VIEW"];
		}

		return $viewId;
	}

	protected function getViewSort()
	{
		$viewSort = $this->defaultViewSort;

		if (isset($this->arParams["RENDER_FILTER_INTO_VIEW_SORT"]) &&
			!empty($this->arParams["RENDER_FILTER_INTO_VIEW_SORT"]))
		{
			$viewSort = (int) $this->arParams["RENDER_FILTER_INTO_VIEW_SORT"];
		}

		return $viewSort;
	}

	protected function prepareSourcePresets()
	{
		$sourcePresets = $this->arParams["FILTER_PRESETS"];
		$presets = array();

		if (!empty($sourcePresets) && is_array($sourcePresets))
		{
			$preset = array();

			foreach ($sourcePresets as $presetId => $presetFields)
			{
				$rows = array_keys($presetFields["fields"]);
				$preset["ID"] = $presetId;
				$preset["TITLE"] = $presetFields["name"];
				$preset["FIELDS"] = $this->preparePresetFields($rows, $presetFields["fields"]);
				$preset["IS_DEFAULT"] = true;
				$preset["PINNED"] = $presetFields["default"] == true;

				$presets[] = $preset;
			}
		}

		global $USER;
		if (!$USER->CanDoOperation("edit_other_settings"))
		{
			$commonOptions = $this->getCommonOptions();

			if (!empty($commonOptions) &&
				is_array($commonOptions) &&
				isset($commonOptions["filters"]) &&
				is_array($commonOptions["filters"]) &&
				isset($commonOptions["filters"]["default_filter"]))
			{
				$rows = explode(",", $commonOptions["filters"]["default_filter"]["filter_rows"]);
			}
			else
			{
				$rows = array_keys($this->prepareFilterRowsParam());
			}
		}
		else
		{
			$rows = array_keys($this->prepareFilterRowsParam());
		}

		$presets[] = array(
			"ID" => "default_filter",
			"TITLE" => Loc::getMessage("MAIN_UI_FILTER__DEFAULT_FILTER_TITLE"),
			"FIELDS" => $this->preparePresetFields($rows, $rows),
			"IS_DEFAULT" => true
		);

		return $presets;
	}

	protected function preparePresets()
	{
		if (!is_array($this->arResult["PRESETS"]))
		{
			$this->arResult["PRESETS"] = $this->prepareSourcePresets();
		}

		return $this->arResult["PRESETS"];
	}

	protected function getField($fieldId)
	{
		$fields = $this->prepareFields();
		$resultField = array();

		if (!empty($fields) && is_array($fields))
		{
			foreach ($fields as $fieldKey => $fieldFields)
			{
				if ($fieldFields["NAME"] === $fieldId ||
					$fieldFields["NAME"]."_datesel" === $fieldId ||
					$fieldFields["NAME"]."_numsel" === $fieldId)
				{
					$resultField = $fieldFields;
				}
			}
		}

		return $resultField;
	}

	protected function prepareFields()
	{
		if (!is_array($this->arResult["FIELDS"]))
		{
			$this->arResult["FIELDS"] = array();
			$sourceFields = $this->arParams["FILTER"];

			if (is_array($sourceFields) && !empty($sourceFields))
			{
				foreach ($sourceFields as $sourceFieldKey => $sourceField)
				{
					switch ($sourceField["type"])
					{
						case "list" : {
							$items = array();

							if (isset($sourceField["items"]) && !empty($sourceField["items"]) && is_array($sourceField["items"]))
							{
								foreach ($sourceField["items"] as $selectItemValue => $selectItemText)
								{
									$items[] = array("NAME" => $selectItemText, "VALUE" => $selectItemValue);
								}
							}

							if ($sourceField["params"]["multiple"] === "Y")
							{
								$field = Field::multiSelect($sourceField["id"], $items, array(), $sourceField["name"], $sourceField["placeholder"]);
							}
							else
							{
								if (empty($items[0]["VALUE"]) && empty($items[0]["NAME"]))
								{
									$items[0]["NAME"] = Loc::getMessage("MAIN_UI_FILTER__NOT_SET");
								}

								if (!empty($items[0]["VALUE"]) && !empty($items[0]["NAME"]))
								{
									array_unshift($items, array("NAME" => Loc::getMessage("MAIN_UI_FILTER__NOT_SET"), "VALUE" => ""));
								}

								$field = Field::select($sourceField["id"], $items, array(), $sourceField["name"], $sourceField["placeholder"]);
							}

							break;
						}

						case "date" : {
							$field = Field::date($sourceField["id"], DateType::NONE, array(), $sourceField["name"], $sourceField["placeholder"], $sourceField["time"], $sourceField["exclude"]);
							break;
						}

						case "number" : {
							$field = Field::number($sourceField["id"], NumberType::SINGLE, array(), $sourceField["name"], $sourceField["placeholder"]);
							$subTypes = array();
							$subType = is_array($field["SUB_TYPE"]) ? $field["SUB_TYPE"]["VALUE"] : $field["SUB_TYPE"];
							$dateTypesList = NumberType::getList();

							foreach ($dateTypesList as $key => $type)
							{
								$subTypes[] = array(
									"NAME" => Loc::getMessage("MAIN_UI_FILTER__NUMBER_".$key),
									"PLACEHOLDER" => "",
									"VALUE" => $type
								);

								if ($type === $subType)
								{
									$field["SUB_TYPE"] = array(
										"NAME" => Loc::getMessage("MAIN_UI_FILTER__NUMBER_".$key),
										"PLACEHOLDER" => "",
										"VALUE" => $subType
									);
								}
							}

							$field["SUB_TYPES"] = $subTypes;

							break;
						}

						case "custom" : {
							$field = Field::custom($sourceField["id"], $sourceField["value"], $sourceField["name"], $sourceField["placeholder"], $sourceField["style"]);
							break;
						}

						case "custom_entity" : {
							$field = Field::customEntity($sourceField["id"], $sourceField["name"], $sourceField["placeholder"]);
							break;
						}

						case "checkbox" : {

							$values = isset($sourceField["valueType"]) && $sourceField["valueType"] === "numeric"
								? array("1", "0")
								: array("Y", "N");

							$items = array(
								array("NAME" => Loc::getMessage("MAIN_UI_FILTER__NOT_SET"), "VALUE" => ""),
								array("NAME" => Loc::getMessage("MAIN_UI_FILTER__YES"), "VALUE" => $values[0]),
								array("NAME" => Loc::getMessage("MAIN_UI_FILTER__NO"), "VALUE" => $values[1])
							);

							$field = Field::select($sourceField["id"], $items, $items[0], $sourceField["name"], $sourceField["placeholder"]);

							break;
						}

						default : {
							$field = Field::string($sourceField["id"], "", $sourceField["name"], $sourceField["placeholder"]);
							break;
						}
					}

					$this->arResult["FIELDS"][] = $field;
				}
			}
		}

		return $this->arResult["FIELDS"];
	}

	protected function prepareSettingsUrl()
	{
		$path = $this->getPath();
		return join("/", array($path, "settings.ajax.php"));
	}

	protected function checkRequiredParams()
	{
		$errors = new \Bitrix\Main\ErrorCollection();

		if (!isset($this->arParams["FILTER_ID"]) ||
			empty($this->arParams["FILTER_ID"]) ||
			!is_string($this->arParams["FILTER_ID"]))
		{
			$errors->add(array(new \Bitrix\Main\Error(Loc::getMessage("MAIN_UI_FILTER__FILTER_ID_NOT_SET"))));
		}

		foreach ($errors->toArray() as $key => $error)
		{
			ShowError($error);
		}

		return $errors->count() === 0;
	}

	protected function getJsFolder()
	{
		return $this->jsFolder;
	}

	protected function getBlocksFolder()
	{
		return $this->blocksFolder;
	}

	protected function getCssFolder()
	{
		return $this->cssFolder;
	}

	protected function includeScripts($folder)
	{
		$tmpl = $this->getTemplate();
		$absPath = $_SERVER["DOCUMENT_ROOT"].$tmpl->GetFolder().$folder;
		$relPath = $tmpl->GetFolder().$folder;

		if (is_dir($absPath))
		{
			$dir = opendir($absPath);

			if($dir)
			{
				while(($file = readdir($dir)) !== false)
				{
					$ext = getFileExtension($file);

					if ($ext === 'js' && !(strpos($file, 'map.js') !== false || strpos($file, 'min.js') !== false))
					{
						$tmpl->addExternalJs($relPath.$file);
					}
				}

				closedir($dir);
			}
		}
	}

	protected function includeComponentBlocks()
	{
		$blocksFolder = $this->getBlocksFolder();
		$this->includeScripts($blocksFolder);
	}

	protected function includeComponentScripts()
	{
		$scriptsFolder = $this->getJsFolder();
		$this->includeScripts($scriptsFolder);
	}

	protected function saveOptions()
	{
		$request = $this->request;

		if ($request->getPost("apply_filter") === "Y")
		{
			$options = $this->getUserOptions();
			$options->setFilterSettings($request->get("filter_id"), $request->toArray());
			$options->save();
		}
	}

	protected function prepareDefaultPresets()
	{
		$this->arResult["DEFAULT_PRESETS"] = $this->prepareSourcePresets();
	}

	protected function getCommonOptions()
	{
		if (!$this->commonOptions)
		{
			$this->commonOptions = \CUserOptions::getOption("main.ui.filter.common", $this->arParams["FILTER_ID"], array());
		}

		return $this->commonOptions;
	}

	protected function initParams()
	{
		global $USER;
		if (!$USER->CanDoOperation("edit_other_settings"))
		{
			$commonOptions = $this->getCommonOptions();
			$filters = $commonOptions["filters"];

			if (!empty($filters) && is_array($filters))
			{
				unset($filters["tmp_filter"]);
				$this->arParams["FILTER_PRESETS"] = $filters;
			}
		}

		if (!isset($this->arParams["FILTER_PRESETS"]) || !is_array($this->arParams["FILTER_PRESETS"]))
		{
			$this->arParams["FILTER_PRESETS"] = array();
		}
	}

	public function executeComponent()
	{
		if ($this->checkRequiredParams())
		{
			$this->initParams();
			$this->prepareDefaultPresets();
			$this->saveOptions();
			$this->prepareParams();
			$this->prepareResult();
			$this->applyOptions();
			$this->includeComponentTemplate();
			$this->includeComponentScripts();
			$this->includeComponentBlocks();
		}
	}
}