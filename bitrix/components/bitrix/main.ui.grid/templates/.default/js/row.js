;(function() {
	'use strict';

	BX.namespace('BX.Grid');

	/**
	 * BX.Grid.Row
	 * @param {BX.Main.Grid} parent
	 * @param {HtmlElement} node
	 * @constructor
	 */
	BX.Grid.Row = function(parent, node)
	{
		this.node = null;
		this.checkbox = null;
		this.sort = null;
		this.actions = null;
		this.settings = null;
		this.index = null;
		this.actionsButton = null;
		this.parent = null;
		this.depth = null;
		this.parentId = null;
		this.editData = null;
		this.custom = null;
		this.init(parent, node);
	};

	//noinspection JSUnusedGlobalSymbols,JSUnusedGlobalSymbols
	BX.Grid.Row.prototype = {
		init: function(parent, node)
		{
			if (BX.type.isDomNode(node))
			{
				this.node = node;
				this.parent = parent;
				this.settings = new BX.Grid.Settings();

				if (this.parent.getParam('ALLOW_CONTEXT_MENU'))
				{
					BX.bind(this.getNode(), 'contextmenu', BX.delegate(this._onRightClick, this));
				}
			}
		},

		isCustom: function()
		{
			if (this.custom === null)
			{
				this.custom = BX.hasClass(this.getNode(), this.parent.settings.get('classRowCustom'));
			}

			return this.custom;
		},

		_onRightClick: function(event)
		{
			event.preventDefault();
			this.showActionsMenu(event);
		},

		getDefaultAction: function()
		{
			return BX.data(this.getNode(), 'default-action');
		},

		editGetValues: function()
		{
			var self = this;
			var cells = this.getCells();
			var values = {};
			var value;

			[].forEach.call(cells, function(current) {
				value = self.getCellEditorValue(current);

				if (value)
				{
					values[value.NAME] = value.VALUE !== undefined ? value.VALUE : "";
				}
			});

			return values;
		},

		getCellEditorValue: function(cell)
		{
			var editor = BX.Grid.Utils.getByClass(cell, this.parent.settings.get('classEditor'), true);
			var result = null;

			if (BX.type.isDomNode(editor))
			{
				if (BX.hasClass(editor, 'main-grid-editor-checkbox'))
				{
					result = {
						'NAME': editor.getAttribute('name'),
						'VALUE': editor.checked ? 'Y' : 'N'
					};
				}
				else
				{
					if (editor.value)
					{
						result = {
							'NAME': editor.getAttribute('name'),
							'VALUE': editor.value
						};
					}
					else
					{
						result = {
							'NAME': editor.getAttribute('name'),
							'VALUE': BX.data(editor, 'value')
						};
					}
				}
			}

			return result;
		},

		isEdit: function()
		{
			return BX.hasClass(this.getNode(), 'main-grid-row-edit');
		},

		hide: function()
		{
			BX.addClass(this.getNode(), this.parent.settings.get('classHide'));
		},

		show: function()
		{
			BX.removeClass(this.getNode(), this.parent.settings.get('classHide'));
		},

		isShown: function()
		{
			return !BX.hasClass(this.getNode(), this.parent.settings.get('classHide'));
		},

		isNotCount: function()
		{
			return BX.hasClass(this.getNode(), this.parent.settings.get('classNotCount'));
		},

		getContentContainer: function(target)
		{
			var result = null;

			if (!BX.hasClass(target, this.parent.settings.get('classCellContainer')))
			{
				if (target.nodeName === 'TD' || target.nodeName === 'TR')
				{
					result = BX.Grid.Utils.getByClass(target, this.parent.settings.get('classCellContainer'), true);
				}
				else
				{
					result = BX.findParent(target, {className: this.parent.settings.get('classCellContainer')}, true, false);
				}
			}
			else
			{
				result = target;
			}

			return result;
		},

		getContent: function(cell)
		{
			var container = this.getContentContainer(cell);
			var content;

			if (BX.type.isDomNode(container))
			{
				content = BX.html(container);
			}

			return content;
		},


		/**
		 * @param {HTMLTableCellElement} cell
		 * @return {?HTMLElement}
		 */
		getEditorContainer: function(cell)
		{
			return BX.Grid.Utils.getByClass(cell, this.parent.settings.get('classEditorContainer'), true);
		},


		/**
		 * @return {HTMLElement}
		 */
		getCollapseButton: function()
		{
			if (!this.collapseButton)
			{
				this.collapseButton = BX.Grid.Utils.getByClass(this.getNode(), this.parent.settings.get('classCollapseButton'), true);
			}

			return this.collapseButton;
		},

		stateLoad: function()
		{
			BX.addClass(this.getNode(), this.parent.settings.get('classRowStateLoad'));
		},

		stateUnload: function()
		{
			BX.removeClass(this.getNode(), this.parent.settings.get('classRowStateLoad'));
		},

		stateExpand: function()
		{
			BX.addClass(this.getNode(), this.parent.settings.get('classRowStateExpand'));
		},

		stateCollapse: function()
		{
			BX.removeClass(this.getNode(), this.parent.settings.get('classRowStateExpand'));
		},

		getParentId: function()
		{
			if (this.parentId === null)
			{
				this.parentId = BX.data(this.getNode(), 'parent-id');
			}

			return this.parentId;
		},


		/**
		 * @return {DOMStringMap}
		 */
		getDataset: function()
		{
			return this.getNode().dataset;
		},


		/**
		 * Gets row depth level
		 * @return {?number}
		 */
		getDepth: function()
		{
			if (this.depth === null)
			{
				this.depth = BX.data(this.getNode(), 'depth');
			}

			return this.depth;
		},


		/**
		 * Set row depth
		 * @param {number} depth
		 */
		setDepth: function(depth)
		{
			depth = parseInt(depth);

			if (BX.type.isNumber(depth))
			{
				var depthOffset = depth - parseInt(this.getDepth());
				var Rows = this.parent.getRows();

				this.getDataset().depth = depth;

				this.getShiftCells().forEach(function(cell) {
					BX.data(cell, 'depth', depth);
					BX.style(cell, 'padding-left', (depth * 20) + 'px');
				}, this);

				Rows.getRowsByParentId(this.getId(), true).forEach(function(row) {
					var childDepth = parseInt(depthOffset) + parseInt(row.getDepth());
					row.getDataset().depth = childDepth;
					row.getShiftCells().forEach(function(cell) {
						BX.data(cell, 'depth', childDepth);
						BX.style(cell, 'padding-left', (childDepth * 20) + 'px');
					});
				});
			}
		},


		/**
		 * Sets parent id
		 * @param {*} id
		 */
		setParentId: function(id)
		{
			var dataset = this.getDataset();
			dataset['parentId'] = id;
		},


		/**
		 * @return {HTMLTableRowElement}
		 */
		getShiftCells: function()
		{
			return BX.Grid.Utils.getBySelector(this.getNode(), 'td[data-shift="true"]');
		},

		showChildRows: function()
		{
			var rows = this.getChildren();
			var isCustom = this.isCustom();

			rows.forEach(function(row) {
				row.show();
				if (!isCustom && row.isExpand())
				{
					row.showChildRows();
				}
			});

			this.parent.updateCounterDisplayed();
			this.parent.updateCounterSelected();
			this.parent.adjustCheckAllCheckboxes();
			this.parent.adjustRows();
		},


		/**
		 * @return {BX.Grid.Row[]}
		 */
		getChildren: function()
		{
			var functionName = this.isCustom() ? 'getRowsByGroupId' : 'getRowsByParentId';
			var id = this.isCustom() ? this.getGroupId() : this.getId();
			return this.parent.getRows()[functionName](id, true);
		},

		hideChildRows: function()
		{
			var rows = this.getChildren();
			rows.forEach(function(row) { row.hide(); });
			this.parent.updateCounterDisplayed();
			this.parent.updateCounterSelected();
			this.parent.adjustCheckAllCheckboxes();
			this.parent.adjustRows();
		},

		isChildsLoaded: function()
		{
			if (!BX.type.isBoolean(this.childsLoaded))
			{
				this.childsLoaded = this.isCustom() || BX.data(this.getNode(), 'child-loaded') === 'true';
			}

			return this.childsLoaded;
		},

		expand: function()
		{
			var self = this;
			this.stateExpand();

			if (this.isChildsLoaded())
			{
				this.showChildRows();
			}
			else
			{
				this.stateLoad();
				this.loadChildRows(function(rows) {
					rows.reverse().forEach(function(current) {
						BX.insertAfter(current, self.getNode());
					});
					self.parent.getRows().reset();
					self.parent.bindOnRowEvents();

					if (self.parent.getParam('ALLOW_ROWS_SORT'))
					{
						self.parent.getRowsSortable().reinit();
					}

					if (self.parent.getParam('ALLOW_COLUMNS_SORT'))
					{
						self.parent.getColsSortable().reinit();
					}

					self.stateUnload();
					BX.data(self.getNode(), 'child-loaded', 'true');
					self.parent.updateCounterDisplayed();
					self.parent.updateCounterSelected();
					self.parent.adjustCheckAllCheckboxes();
				});
			}
		},

		collapse: function()
		{
			this.stateCollapse();
			this.hideChildRows();
		},

		isExpand: function()
		{
			return BX.hasClass(this.getNode(), this.parent.settings.get('classRowStateExpand'));
		},

		toggleChildRows: function()
		{
			if (!this.isExpand())
			{
				this.expand();
			}
			else
			{
				this.collapse();
			}
		},

		loadChildRows: function(callback)
		{
			if (BX.type.isFunction(callback))
			{
				var self = this;
				var depth = parseInt(this.getDepth());
				var action = this.parent.getUserOptions().getAction('GRID_GET_CHILD_ROWS');
				depth = BX.type.isNumber(depth) ? depth+1 : 1;
				this.parent.getData().request('', 'POST', {action: action, parent_id: this.getId(), depth: depth}, null, function() {
					var rows = this.getRowsByParentId(self.getId());
					callback.apply(null, [rows]);
				});
			}
		},

		update: function(data, url, callback)
		{
			var action = this.parent.getUserOptions().getAction('GRID_UPDATE_ROW');
			var depth = this.getDepth();
			var id = this.getId();
			var parentId = this.getParentId();
			var rowData = {id: id, parentId: parentId, action: action, depth: depth, data: data};
			var self = this;

			this.stateLoad();
			this.parent.getData().request(url, 'POST', rowData, null, function() {
				var bodyRows = this.getBodyRows();
				self.parent.getUpdater().updateBodyRows(bodyRows);
				self.stateUnload();
				self.parent.getRows().reset();
				self.parent.getUpdater().updateFootRows(this.getFootRows());
				self.parent.getUpdater().updatePagination(this.getPagination());
				self.parent.getUpdater().updateMoreButton(this.getMoreButton());
				self.parent.getUpdater().updateCounterTotal(this.getCounterTotal());
				self.parent.bindOnRowEvents();
				self.parent.adjustEmptyTable(bodyRows);

				self.parent.bindOnMoreButtonEvents();
				self.parent.bindOnClickPaginationLinks();
				self.parent.updateCounterDisplayed();
				self.parent.updateCounterSelected();

				if (self.parent.getParam('ALLOW_COLUMNS_SORT'))
				{
					self.parent.colsSortable.reinit();
				}

				if (self.parent.getParam('ALLOW_ROWS_SORT'))
				{
					self.parent.rowsSortable.reinit();
				}

				BX.onCustomEvent(window, 'Grid::rowUpdated', [{id: id, data: data, grid: self.parent, response: this}]);
				BX.onCustomEvent(window, 'Grid::updated', []);

				if (BX.type.isFunction(callback))
				{
					callback({id: id, data: data, grid: self.parent, response: this});
				}
			});
		},

		remove: function(data, url, callback)
		{
			var action = this.parent.getUserOptions().getAction('GRID_DELETE_ROW');
			var depth = this.getDepth();
			var id = this.getId();
			var parentId = this.getParentId();
			var rowData = {id: id, parentId: parentId, action: action, depth: depth, data: data};
			var self = this;

			this.stateLoad();
			this.parent.getData().request(url, 'POST', rowData, null, function() {
				var bodyRows = this.getBodyRows();
				self.parent.getUpdater().updateBodyRows(bodyRows);
				self.stateUnload();
				self.parent.getRows().reset();
				self.parent.getUpdater().updateFootRows(this.getFootRows());
				self.parent.getUpdater().updatePagination(this.getPagination());
				self.parent.getUpdater().updateMoreButton(this.getMoreButton());
				self.parent.getUpdater().updateCounterTotal(this.getCounterTotal());
				self.parent.bindOnRowEvents();
				self.parent.adjustEmptyTable(bodyRows);

				self.parent.bindOnMoreButtonEvents();
				self.parent.bindOnClickPaginationLinks();
				self.parent.updateCounterDisplayed();
				self.parent.updateCounterSelected();

				if (self.parent.getParam('ALLOW_COLUMNS_SORT'))
				{
					self.parent.colsSortable.reinit();
				}

				if (self.parent.getParam('ALLOW_ROWS_SORT'))
				{
					self.parent.rowsSortable.reinit();
				}

				BX.onCustomEvent(window, 'Grid::rowRemoved', [{id: id, data: data, grid: self.parent, response: this}]);
				BX.onCustomEvent(window, 'Grid::updated', []);

				if (BX.type.isFunction(callback))
				{
					callback({id: id, data: data, grid: self.parent, response: this});
				}
			});
		},

		editCancel: function()
		{
			var cells = this.getCells();
			var self = this;
			var editorContainer;

			[].forEach.call(cells, function(current) {
				editorContainer = self.getEditorContainer(current);

				if (BX.type.isDomNode(editorContainer))
				{
					BX.remove(self.getEditorContainer(current));
					BX.show(self.getContentContainer(current));
				}
			});

			BX.removeClass(this.getNode(), 'main-grid-row-edit');
		},

		getCellByIndex: function(index)
		{
			return this.getCells()[index];
		},

		getEditDataByCellIndex: function(index)
		{
			return eval(BX.data(this.getCellByIndex(index), 'edit'));
		},

		getCellNameByCellIndex: function(index)
		{
			return BX.data(this.getCellByIndex(index), 'name');
		},

		getEditData: function()
		{
			if (this.editData === null)
			{
				var editableData = this.parent.getParam('EDITABLE_DATA');
				var rowId = this.getId();

				if (BX.type.isPlainObject(editableData) && rowId in editableData)
				{
					this.editData = editableData[rowId];
				}
				else
				{
					this.editData = {};
				}
			}

			return this.editData
		},

		getCellEditDataByCellIndex: function(cellIndex)
		{
			var editData = this.getEditData();
			var result = null;
			cellIndex = parseInt(cellIndex);

			if (BX.type.isNumber(cellIndex) && BX.type.isPlainObject(editData))
			{
				var columnEditData = this.parent.getRows().getHeadFirstChild().getEditDataByCellIndex(cellIndex);

				if (BX.type.isPlainObject(columnEditData))
				{
					result = columnEditData;
					result.VALUE = editData[columnEditData.NAME];
				}
			}

			return result;
		},

		edit: function()
		{
			var cells = this.getCells();
			var self = this;
			var editObject, editor, height, contentContainer;

			[].forEach.call(cells, function(current, index) {
				try {
					editObject = self.getCellEditDataByCellIndex(index);
				} catch (err) {
					throw new Error(err);
				}

				if (self.parent.getEditor().validateEditObject(editObject))
				{
					contentContainer = self.getContentContainer(current);
					height = BX.height(contentContainer);
					editor = self.parent.getEditor().getEditor(editObject, height);

					if (!self.getEditorContainer(current) && BX.type.isDomNode(editor))
					{
						current.appendChild(editor);
						BX.hide(contentContainer);
					}
				}
			});

			BX.addClass(this.getNode(), 'main-grid-row-edit');
		},

		setDraggable: function(value)
		{
			if (!value)
			{
				BX.addClass(this.getNode(), this.parent.settings.get('classDisableDrag'));
				this.parent.getRowsSortable().unregister(this.getNode());
			}
			else
			{
				BX.removeClass(this.getNode(), this.parent.settings.get('classDisableDrag'));
				this.parent.getRowsSortable().register(this.getNode());
			}
		},

		isDraggable: function()
		{
			return !BX.hasClass(this.getNode(), this.parent.settings.get('classDisableDrag'));
		},

		getNode: function()
		{
			return this.node;
		},

		getIndex: function()
		{
			return this.getNode().rowIndex;
		},

		getId: function()
		{
			return BX.data(this.getNode(), 'id');
		},

		getGroupId: function()
		{
			return BX.data(this.getNode(), 'group-id');
		},

		getObserver: function()
		{
			return BX.Grid.observer;
		},

		getCheckbox: function()
		{
			if (!this.checkbox)
			{
				this.checkbox = BX.Grid.Utils.getByClass(this.getNode(), this.settings.get('classRowCheckbox'), true);
			}

			return this.checkbox;
		},

		getActionsMenu: function()
		{
			if (!this.actionsMenu)
			{
				var buttonRect = this.getActionsButton().getBoundingClientRect();

				this.actionsMenu = BX.PopupMenu.create(
					'main-grid-actions-menu-' + this.getIndex(),
					this.getActionsButton(),
					this.getMenuItems(),
					{
						'autoHide': true,
						'offsetTop': -((buttonRect.height / 2) + 26),
						'offsetLeft': 30,
						'angle': {
							'position': 'left',
							'offset': ((buttonRect.height / 2) - 8)
						},
						'events': {
							'onPopupClose': BX.delegate(this._onCloseMenu, this)
						}
					}
				);

				BX.bind(this.actionsMenu.popupWindow.popupContainer, 'click', BX.delegate(function() {
					var actionsMenu = this.getActionsMenu();
					if (actionsMenu)
					{
						actionsMenu.close();
					}
				}, this));
			}

			return this.actionsMenu;
		},

		_onCloseMenu: function()
		{
		},

		actionsMenuIsShown: function()
		{
			return this.getActionsMenu().popupWindow.isShown();
		},

		showActionsMenu: function(event)
		{
			var pos = BX.pos(this.getActionsButton());

			BX.PopupMenu.destroy('main-grid-actions-menu-' + this.getIndex());
			this.actionsMenu = null;

			if (event)
			{
				BX.fireEvent(document.body, 'click');
				this.getActionsMenu().popupWindow.setOffset({
					offsetLeft: (event.pageX - pos.left) + 20,
					offsetTop: (event.pageY - pos.top) - 30
				});
			}
			else
			{
				this.getActionsMenu().popupWindow.adjustPosition(pos);
			}

			this.getActionsMenu().popupWindow.show();
		},

		closeActionsMenu: function()
		{
			if(this.actionsMenu)
			{
				if(this.actionsMenu.popupWindow)
				{
					this.actionsMenu.popupWindow.close();
				}
				this.actionsMenu = null;
			}
		},

		getMenuItems: function()
		{
			return this.getActions() || [];
		},

		getActions: function()
		{
			try {
				this.actions = this.actions || eval(BX.data(this.getActionsButton(), this.settings.get('dataActionsKey')));
			} catch (err) {
				this.actions = null;
			}

			return this.actions;
		},

		getActionsButton: function()
		{
			if (!this.actionsButton)
			{
				this.actionsButton = BX.Grid.Utils.getByClass(this.getNode(), this.settings.get('classRowActionButton'), true);
			}

			return this.actionsButton;
		},

		initSelect: function()
		{
			if (this.isSelected() && !BX.hasClass(this.getNode(), this.settings.get('classCheckedRow')))
			{
				BX.addClass(this.getNode(), this.settings.get('classCheckedRow'))
			}
		},

		getParentNode: function()
		{
			var result;

			try {
				result = (this.getNode()).parentNode;
			} catch (err) {
				result = null;
			}

			return result;
		},

		getParentNodeName: function()
		{
			var result;

			try {
				result = (this.getParentNode()).nodeName;
			} catch (err) {
				result = null;
			}

			return result;
		},

		select: function()
		{
			var checkbox;

			if (!this.isEdit())
			{
				checkbox = this.getCheckbox();

				if (checkbox)
				{
					if (!BX.data(checkbox, 'disabled'))
					{
						BX.addClass(this.getNode(), this.settings.get('classCheckedRow'));
						checkbox.checked = true;
					}
				}
			}
		},

		unselect: function()
		{
			if (!this.isEdit())
			{
				BX.removeClass(this.getNode(), this.settings.get('classCheckedRow'));
				if (this.getCheckbox())
				{
					this.getCheckbox().checked = false;
				}
			}
		},

		getCells: function()
		{
			return this.getNode().cells;
		},

		isSelected: function()
		{
			return (
				(this.getCheckbox() && (this.getCheckbox()).checked) ||
				(BX.hasClass(this.getNode(), this.settings.get('classCheckedRow')))
			);
		},

		isHeadChild: function()
		{
			return (
				this.getParentNodeName() === 'THEAD' &&
				BX.hasClass(this.getNode(), this.settings.get('classHeadRow'))
			);
		},

		isBodyChild: function()
		{
			return (
				BX.hasClass(this.getNode(), this.settings.get('classBodyRow')) && !BX.hasClass(this.getNode(), this.settings.get('classEmptyRows'))
			);
		},

		isFootChild: function()
		{
			return (
				this.getParentNodeName() === 'TFOOT' &&
				BX.hasClass(this.getNode(), this.settings.get('classFootRow'))
			);
		}
	};
})();