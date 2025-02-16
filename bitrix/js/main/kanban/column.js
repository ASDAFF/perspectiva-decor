;(function() {

"use strict";

BX.namespace("BX.Kanban");

/**
 *
 * @param {object} options
 * @param {string|number} options.id
 * @param {string} [options.name]
 * @param {string} [options.color]
 * @param {object} [options.data]
 * @param {number} [options.total]
 * @constructor
 */
BX.Kanban.Column = function(options)
{
	options = options || {};
	if (!BX.Kanban.Utils.isValidId(options.id))
	{
		throw new Error("BX.Kanban.Column: 'id' parameter is not valid.")
	}

	this.id = options.id;
	this.name = null;
	this.color = null;
	this.data = Object.create(null);
	this.total = null;
	this.isTotalFrozen = false;

	this.canEdit = null;
	this.canSort = null;
	this.canRemove = null;
	this.canAddItem = null;

	this.droppable = true;

	this.setOptions(options);

	/**@var {BX.Kanban.Item[]} **/
	this.items = [];

	/** @var {BX.Kanban.Grid} **/
	this.grid = null;

	this.page = 1;

	this.layout = {
		container: null,
		items: null,
		dragTarget: null,
		title: null,
		subTitle: null,
		subTitleAddButton: null,
		total: null,
		name: null,
		titleArrow: null,
		color: null,
		editForm: null,
		fillColorButton: null,
		titleTextBox: null,
		addColumnButton: null,
		editButton: null,
		removeButton: null
	};

	this.rectArea = null;

	this.dragColumnOffset = null;
	this.dragColumnIndex = null;
	this.dragTargetColumn = null;

	this.confirmDialog = null;
	this.textBoxTimeout = null;
	this.colorChanged = false;

	this.pagination = new BX.Kanban.Pagination(this);
};

BX.Kanban.Column.DEFAULT_COLOR = "ace9fb";

BX.Kanban.Column.prototype =
{
	/**
	 *
	 * @returns {number|string}
	 */
	getId: function()
	{
		return this.id;
	},

	setOptions: function(options)
	{
		if (!options)
		{
			return;
		}

		this.setName(options.name);
		this.setTotal(options.total);
		this.setColor(options.color);
		this.setData(options.data);

		var boolOptions = ["canEdit", "canSort", "canRemove", "canAddItem", "droppable"];
		boolOptions.forEach(function(boolOption) {
			if (BX.type.isBoolean(options[boolOption]))
			{
				this[boolOption] = options[boolOption];
			}
		}, this);
	},

	setColor: function(color)
	{
		if (BX.Kanban.Utils.isValidColor(color))
		{
			this.color = color.toLowerCase();
		}
	},

	getColor: function()
	{
		return this.color !== null ? this.color : BX.Kanban.Column.DEFAULT_COLOR;
	},

	/**
	 * @param {BX.Kanban.Grid} grid
	 * @internal
	 */
	setGrid: function(grid)
	{
		this.grid = grid;
		BX.onCustomEvent(this, "Kanban.Column:onAddedToGrid", [this]);
	},

	/**
	 * @returns {BX.Kanban.Grid}
	 */
	getGrid: function()
	{
		return this.grid;
	},

	/**
	 *
	 * @returns {BX.Kanban.Pagination}
	 */
	getPagination: function()
	{
		return this.pagination;
	},

	/**
	 *
	 * @param {BX.Kanban.Item} item
	 * @param {BX.Kanban.Item} [beforeItem]
	 * @internal
	 */
	addItem: function(item, beforeItem)
	{
		if (!item instanceof BX.Kanban.Item)
		{
			throw new Error("item must be an instance of BX.Kanban.Item");
		}

		item.setColumnId(this.getId());
		//? setGrid

		var index = BX.util.array_search(beforeItem, this.items);
		if (index >= 0)
		{
			this.items.splice(index, 0, item);
		}
		else
		{
			this.items.push(item);
		}

		if (item.isCountable())
		{
			this.incrementTotal();
		}

		if (this.getGrid().isRendered())
		{
			this.render();
		}
	},

	/**
	 *
	 * @returns {BX.Kanban.Item[]}
	 */
	getItems: function()
	{
		return this.items;
	},

	getItemsCount: function()
	{
		return this.items.reduce(function(count, /*BX.Kanban.Item*/ item) {
			return item.isCountable() ? count + 1 : count;
		}, 0);
	},

	/**
	 *
	 * @returns {BX.Kanban.Item|null}
	 */
	getFirstItem: function()
	{
		var items = this.getItems();

		return items[0] ? items[0] : null;
	},

	/**
	 *
	 * @returns {BX.Kanban.Item|null}
	 */
	getLastItem: function()
	{
		var items = this.getItems();
		var count = this.getItemsCount();

		return count > 0 ? items[count - 1] : null;
	},

	/**
	 *
	 * @param {BX.Kanban.Item|string|number} currentItem
	 * @returns {BX.Kanban.Item|null}
	 */
	getNextItemSibling: function(currentItem)
	{
		currentItem  = this.getGrid().getItem(currentItem);

		var items = this.getItems();
		var itemIndex = BX.util.array_search(currentItem, items);

		return itemIndex !== -1 && items[itemIndex + 1] ? items[itemIndex + 1] : null;
	},

	/**
	 *
	 * @param {BX.Kanban.Item|string|number} currentItem
	 * @returns {BX.Kanban.Item|null}
	 */
	getPreviousItemSibling: function(currentItem)
	{
		currentItem  = this.getGrid().getItem(currentItem);

		var items = this.getItems();
		var itemIndex = BX.util.array_search(currentItem, items);

		return itemIndex > 0 && items[itemIndex - 1] ? items[itemIndex - 1] : null;
	},

	/**
	 *
	 * @param {BX.Kanban.Item} itemToRemove
	 */
	removeItem: function(itemToRemove)
	{
		var found = false;
		this.items = this.items.filter(function(item) {

			if (item === itemToRemove)
			{
				found = true;
				return false;
			}

			return true;
		});

		if (found)
		{
			if (itemToRemove.isCountable())
			{
				this.decrementTotal();
			}

			if (this.getGrid().isRendered())
			{
				this.render();
			}
		}
	},

	removeItems: function()
	{
		this.items = [];
		this.total = null;
		BX.cleanNode(this.layout.items);
		this.render();
	},

	setName: function(name)
	{
		if (BX.type.isNotEmptyString(name))
		{
			this.name = name;
		}
	},

	getName: function()
	{
		return this.name;
	},

	getData: function()
	{
		return this.data;
	},

	setData: function(data)
	{
		if (BX.type.isPlainObject(data))
		{
			this.data = data;
		}
	},

	/**
	 *
	 * @returns {object}
	 */
	getGridData: function()
	{
		return this.getGrid().getData();
	},

	isEditable: function()
	{
		return this.canEdit !== null ? this.canEdit : this.getGrid().canEditColumns();
	},

	isSortable: function()
	{
		return this.canSort !== null ? this.canSort : this.getGrid().canSortColumns();
	},

	isRemovable: function()
	{
		return this.canRemove !== null ? this.canRemove : this.getGrid().canRemoveColumns();
	},

	canAddItems: function()
	{
		return this.canAddItem !== null ? this.canAddItem : this.getGrid().canAddItems();
	},

	/**
	 *
	 * @returns {number}
	 */
	getTotal: function()
	{
		return this.total !== null ? this.total : this.getItemsCount();
	},

	/**
	 *
	 * @param {number} [value=1]
	 */
	incrementTotal: function(value)
	{
		if (this.total !== null && this.getGrid().isRendered() && !this.isTotalFrozen)
		{
			value = BX.type.isNumber(value) ? value : 1;
			this.total = Math.max(this.total + value, this.getItemsCount());
		}
	},

	/**
	 *
	 * @param {number} [value=1]
	 */
	decrementTotal: function(value)
	{
		if (this.total !== null && this.getGrid().isRendered() && !this.isTotalFrozen)
		{
			value = BX.type.isNumber(value) ? value : 1;
			this.total = Math.max(this.total - value, this.getItemsCount());
		}
	},

	freezeTotal: function()
	{
		this.isTotalFrozen = true;
	},

	unfreezeTotal: function()
	{
		this.isTotalFrozen = false;
	},

	setTotal: function(total)
	{
		if (BX.type.isNumber(total) && total >= 0)
		{
			this.total = total;
		}
	},

	refreshTotal: function()
	{
		if (this.total !== null && this.total < this.getItemsCount())
		{
			this.total = this.getItemsCount();
			this.renderTitle();
		}
	},

	hasLoading: function()
	{
		return this.total !== null && this.total > this.getItemsCount();
	},

	getIndex: function()
	{
		return this.getGrid().getColumnIndex(this);
	},

	/**
	 *
	 * @returns {Element}
	 */
	render: function()
	{
		var columnContainer = this.getContainer();
		// columnContainer.style.maxWidth = (this.getGrid().renderTo.offsetWidth / 2) + "px";
		columnContainer.classList[!this.getItems().length ? "add" : "remove"]("main-kanban-column-empty");
		columnContainer.classList[this.isDroppable() ? "add" : "remove"]("main-kanban-column-droppable");

		var title = this.getTitleContainer();
		BX.cleanNode(title);
		title.appendChild(this.renderTitle());

		if (this.getGrid().canAddColumns())
		{
			title.appendChild(this.getAddColumnButton());
		}

		var subTitle = this.getSubTitle();

		BX.cleanNode(subTitle);
		var subTitleContent = this.renderSubTitle();
		if (subTitleContent !== null)
		{
			subTitle.appendChild(subTitleContent);
		}

		BX.cleanNode(this.layout.items);
		for (var i = 0; i < this.items.length; i++)
		{
			var item = this.items[i];
			this.layout.items.appendChild(item.renderLayout());
		}

		if (this.getGrid().isRendered())
		{
			this.getPagination().adjust();
			this.getGrid().adjustEmptyStub();
		}

		return columnContainer;
	},

	/**
	 * Renders title content. It can be overridden.
	 * @returns {Element}
	 */
	renderTitle: function()
	{
		var titleBody = this.getDefaultTitleLayout();

		var isDark = BX.Kanban.Utils.isDarkColor(this.getColor());
		titleBody.classList[isDark ? "add" : "remove"]("main-kanban-column-title-dark");

		this.layout.nameInner.textContent = this.getName();
		this.layout.total.textContent = this.getTotal();

		this.layout.color.style.backgroundColor = "#" + this.getColor();
		this.layout.titleArrow.style.background =
			"transparent url(data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%" +
			"20width%3D%2213%22%20height%3D%2232%22%20viewBox%3D%220%200%2013%2032%22%3E%3Cpath%20fill%3D%22%23" +
			this.getColor() +
			"%22%20fill-opacity%3D%221%22%20d%3D%22M0%200h3c2.8%200%204%203%204%203l6%2013-6%2013s-1.06%203-" +
			"4%203H0V0z%22/%3E%3C/svg%3E) no-repeat"
		;

		return titleBody;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getDefaultTitleLayout: function()
	{
		if (this.layout.titleBody)
		{
			return this.layout.titleBody;
		}

		var customButtons = this.getCustomTitleButtons();
		if (BX.type.isDomNode(customButtons))
		{
			customButtons = [customButtons];
		}
		else if (!BX.type.isArray(customButtons))
		{
			customButtons = [];
		}

		this.layout.titleBody = BX.create("div", {
			attrs: {
				className: "main-kanban-column-title-wrapper"
			},
			children: [
				this.layout.color = BX.create("div", {
					attrs: {
						className: "main-kanban-column-title-bg",
						style: "background: #" + this.getColor()
					}
				}),
				this.layout.info = BX.create("div", {
					attrs: {
						className: "main-kanban-column-title-info"
					},
					children: [

						this.layout.name = BX.create("div", {
							attrs: {
								className: "main-kanban-column-title-text"
							},
							children: [
								this.getColumnTitle(),
								this.getTotalItem()
							]
						}),

						this.isEditable() ? this.getEditButton() : null
					].concat(customButtons)
				}),

				this.isEditable() ? this.getEditForm() : null,

				this.layout.titleArrow = BX.create("span", {
					attrs: {
						className: "main-kanban-column-title-right"

					}
				})

		]});

		return this.layout.titleBody;
	},

	getColumnTitle: function ()
	{
		return this.layout.nameInner = BX.create("div", {
			attrs: {
				className: "main-kanban-column-title-text-inner"
			}
		})
	},

	getTotalItem: function ()
	{
		return this.layout.total = BX.create("div", {
			attrs: {
				className: "main-kanban-column-total-item"
			}
		})
	},

	getEditButton: function()
	{
		if (this.layout.editButton)
		{
			return this.layout.editButton;
		}

		this.layout.editButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-edit"
			},
			events: {
				click: this.switchToEditMode.bind(this)
			}
		});

		return this.layout.editButton;
	},

	getCustomTitleButtons: function()
	{
		return null;
	},

	getRemoveButton: function()
	{
		if (this.layout.removeButton)
		{
			return this.layout.removeButton;
		}

		this.layout.removeButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-remove-button"
			},
			events: {
				click: this.handleRemoveButtonClick.bind(this)
			}
		});

		return this.layout.removeButton;
	},

	getEditForm: function()
	{
		if (this.layout.editForm)
		{
			return this.layout.editForm;
		}

		this.layout.editForm = BX.create("div", {
			attrs: {
				className: "main-kanban-column-title-block-edit"
			},
			children: [
				this.getTitleTextBox(),
				this.getFillColorButton(),
				this.isRemovable() ? this.getRemoveButton() : null
			]
		});

		return this.layout.editForm;
	},

	getTitleTextBox: function()
	{
		if (this.layout.titleTextBox)
		{
			return this.layout.titleTextBox;
		}

		this.layout.titleTextBox = BX.create("input", {
			attrs: {
				className: "main-kanban-column-title-input-edit",
				type: "text",
				placeholder: BX.message("MAIN_KANBAN_TITLE_PLACEHOLDER")
			},
			events: {
				blur: this.handleTextBoxBlur.bind(this),
				keydown: this.handleTextBoxKeyDown.bind(this)
			}
		});

		return this.layout.titleTextBox;
	},

	getFillColorButton: function()
	{
		if (this.layout.fillColorButton)
		{
			return this.layout.fillColorButton;
		}

		this.layout.fillColorButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-color"
			},
			events: {
				click: this.showColorPicker.bind(this)
			}
		});

		return this.layout.fillColorButton;
	},

	switchToEditMode: function()
	{
		this.disableDragging();
		this.getContainer().classList.add("main-kanban-column-edit-mode");
		this.getTitleTextBox().value = this.getName();
		this.focusTextBox();
	},

	applyEditMode: function()
	{
		var title = BX.util.trim(this.getTitleTextBox().value);
		var titleChanged = false;
		if (title.length > 0 && this.getName() !== title)
		{
			titleChanged = true;
		}

		if (titleChanged || this.colorChanged)
		{
			if (titleChanged)
			{
				this.setName(title);
			}

			BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onColumnUpdated", [this]);
			this.render();
		}

		this.colorChanged = false;
		this.enableDragging();
		this.getContainer().classList.remove("main-kanban-column-edit-mode");
	},

	handleTextBoxBlur: function(event)
	{
		this.textBoxTimeout = setTimeout(function() {

			this.applyEditMode();
			this.textBoxTimeout = null;

		}.bind(this), 250);

	},

	stopTextBoxBlur: function()
	{
		if (this.textBoxTimeout)
		{
			clearTimeout(this.textBoxTimeout);
		}
	},

	focusTextBox: function()
	{
		this.getTitleTextBox().focus();
	},

	handleTextBoxKeyDown: function(event)
	{
		if (event.keyCode === 13)
		{
			this.applyEditMode();
		}
	},

	handleRemoveButtonClick: function(event)
	{
		this.showRemoveConfirmDialog();
	},

	showColorPicker: function()
	{
		this.stopTextBoxBlur();
		this.getColorPicker().open();
	},

	/**
	 *
	 * @returns {BX.ColorPicker}
	 */
	getColorPicker: function()
	{
		if (this.colorPicker)
		{
			return this.colorPicker;
		}

		this.colorPicker = new BX.ColorPicker({
			bindElement: this.getFillColorButton(),
			onColorSelected: this.onColorSelected.bind(this),
			popupOptions: {
				events: {
					onPopupClose: this.focusTextBox.bind(this)
				}
			}
		});

		return this.colorPicker;
	},

	/**
	 *
	 * @param {string} color
	 */
	onColorSelected: function(color)
	{
		this.setColor(color.substr(1));
		this.colorChanged = true;
		this.render();
	},

	/**
	 *
	 * @returns {BX.PopupWindow}
	 */
	getConfirmDialog: function()
	{
		if (this.confirmDialog)
		{
			return this.confirmDialog;
		}

		this.confirmDialog = new BX.PopupWindow(
			"main-kanban-confirm-" + BX.util.getRandomString(5),
			null,
			{
				titleBar: BX.message("MAIN_KANBAN_REMOVE_COLUMN_CONFIRM_TITLE"),
				content: BX.message("MAIN_KANBAN_REMOVE_COLUMN_CONFIRM_DESC"),
				width: 400,
				autoHide: false,
				overlay: true,
				closeByEsc : true,
				closeIcon : true,
				draggable : { restrict : true},
				buttons: [
					new BX.PopupWindowButton({
						text: BX.message("MAIN_KANBAN_REMOVE_BUTTON"),
						id: "main-kanban-confirm-remove-button",
						className: "popup-window-button-create",
						events: {
							click: this.handleConfirmButtonClick.bind(this)
						}
					}),
					new BX.PopupWindowButtonLink({
						text: BX.message("MAIN_KANBAN_CANCEL_BUTTON"),
						className: "popup-window-button-link-cancel",
						events: {
							click: function() {
								this.popupWindow.close();
							}
						}
					})
				],
				events: {
					onPopupClose: function() {
						this.focusTextBox();
						this.confirmDialog.destroy();
						this.confirmDialog = null;
					}.bind(this)
				}
			}
		);

		return this.confirmDialog;
	},

	handleConfirmButtonClick: function()
	{
		var confirmDialog = this.getConfirmDialog();
		var removeButton = confirmDialog.getButton("main-kanban-confirm-remove-button");
		if (removeButton.getContainer().classList.contains("popup-window-button-wait"))
		{
			//double click protection
			return;
		}

		removeButton.addClassName("popup-window-button-wait");

		var promise = this.getGrid().getEventPromise(
			"Kanban.Grid:onColumnRemovedAsync",
			null,
			function(result) {

				this.getGrid().removeColumn(this);
				removeButton.removeClassName("popup-window-button-wait");
				confirmDialog.close();

			}.bind(this),
			function(error) {
				confirmDialog.setContent(error);
				removeButton.getContainer().style.display = "none";
			}.bind(this)
		);

		promise.fulfill(this);
	},

	showRemoveConfirmDialog: function()
	{
		this.stopTextBoxBlur();
		var confirmDialog = this.getConfirmDialog();
		confirmDialog.show();
	},

	handleAddColumnButtonClick: function(event)
	{
		var newColumn = this.getGrid().addColumn({
			id: "kanban-new-column-" + BX.util.getRandomString(5),
			type: "BX.Kanban.DraftColumn",
			canSort: false,
			canAddItem: false,
			droppable: false,
			targetId: this.getGrid().getNextColumnSibling(this)
		});

		newColumn.switchToEditMode();
	},

	/**
	 * Renders subtitle content. It can be overridden.
	 * @returns {Element}
	 */
	renderSubTitle: function()
	{
		if (this.layout.subTitleAddButton)
		{
			return this.layout.subTitleAddButton;
		}

		this.layout.subTitleAddButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-subtitle-box"
			},
			children: [
				this.canAddItems()
				? BX.create("div", {
						attrs: {
							className: "main-kanban-column-add-item-button"
						},
						events: {
							click: this.handleAddItemButtonClick.bind(this)
						}
					})
				: null
			]
		});

		return this.layout.subTitleAddButton;
	},

	handleAddItemButtonClick: function(event)
	{
		BX.Kanban.DraftItem.addToColumn(this, this.getFirstItem());
	},

	/**
	 *
	 * @returns {Element}
	 */
	getContainer: function()
	{
		if (this.layout.container !== null)
		{
			return this.layout.container;
		}

		this.layout.container = BX.create("div", {
			attrs: {
				className: "main-kanban-column"
			},
			children: [
				this.getHeader(),
				this.getBody()
			]
		});

		this.makeDraggable();
		this.makeDroppable();

		return this.layout.container;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getHeader: function()
	{
		if (this.layout.header)
		{
			return this.layout.header;
		}

		this.layout.header = BX.create("div", {
			attrs: {
				className: "main-kanban-column-header"
			},
			children: [
				this.getTitleContainer(),
				this.getSubTitle()
			]
		});

		return this.layout.header;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getBody: function()
	{
		if (this.layout.body)
		{
			return this.layout.body;
		}

		this.layout.body = BX.create("div", {
			attrs: {
				className: "main-kanban-column-body",
				"data-id": this.getId(),
				"data-type": "column"
			},
			events: {
				wheel: BX.delegate(this.blockPageScroll, this)
			},
			children: [
				this.getItemsContainer(),
				this.getDragTarget()
			]
		});

		return this.layout.body;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getTitleContainer: function()
	{
		if (this.layout.title)
		{
			return this.layout.title;
		}

		this.layout.title = BX.create("div", {
			attrs: {
				className: "main-kanban-column-title"
			}
		});

		return this.layout.title;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getSubTitle: function()
	{
		if (!this.layout.subTitle)
		{
			this.layout.subTitle = BX.create("div", {
				attrs: {
					className: "main-kanban-column-subtitle"
				}
			})
		}

		return this.layout.subTitle;
	},

	getAddColumnButton: function ()
	{
		if (this.layout.addColumnButton)
		{
			return this.layout.addColumnButton;
		}

		this.layout.addColumnButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-title-add-column"
			},
			events: {
				click: this.handleAddColumnButtonClick.bind(this)
			}
		});

		return this.layout.addColumnButton;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getItemsContainer: function()
	{
		if (!this.layout.items)
		{
			this.layout.items = BX.create("div", {
				attrs: {
					className: "main-kanban-column-items"
				}
			})
		}

		return this.layout.items;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getDragTarget: function()
	{
		if (!this.layout.dragTarget)
		{
			this.layout.dragTarget = BX.create("div", {
				attrs: {
					className: "main-kanban-column-drag-target"
				}
			});
		}

		return this.layout.dragTarget;
	},

	/**
	 *
	 * @param {WheelEvent} event
	 */
	blockPageScroll: function(event)
	{
		var bodyContainer = this.getBody();
		if (bodyContainer.scrollHeight > bodyContainer.offsetHeight)
		{
			var mouseScroll = event.deltaY || event.detail || event.wheelDelta;

			if (mouseScroll < 0 && bodyContainer.scrollTop === 0)
			{
				event.preventDefault();
			}

			if (mouseScroll > 0 && bodyContainer.scrollHeight - bodyContainer.clientHeight - bodyContainer.scrollTop <= 1)
			{
				event.preventDefault();
			}
		}
	},

	makeDraggable: function()
	{
		if (!this.isSortable())
		{
			return;
		}

		var title = this.getTitleContainer();

		//main events
		title.onbxdragstart = BX.delegate(this.onColumnDragStart, this);
		title.onbxdrag = BX.delegate(this.onColumnDrag, this);
		title.onbxdragstop = BX.delegate(this.onColumnDragStop, this);

		this.enableDragging();
	},

	makeDroppable: function()
	{
		if (!this.isDroppable())
		{
			return;
		}

		var columnBody = this.getBody();

		columnBody.onbxdestdraghover = BX.delegate(this.onDragEnter, this);
		columnBody.onbxdestdraghout = BX.delegate(this.onDragLeave, this);
		columnBody.onbxdestdragfinish = BX.delegate(this.onDragDrop, this);

		columnBody.onbxdestdragstop = BX.delegate(this.onItemDragEnd, this);

		jsDD.registerDest(columnBody, 40);

		this.disableDropping();
	},

	disableDragging: function()
	{
		if (this.isSortable())
		{
			jsDD.unregisterObject(this.getTitleContainer());
		}
	},

	enableDragging: function()
	{
		if (this.isSortable())
		{
			jsDD.registerObject(this.getTitleContainer());
		}
	},

	disableDropping: function()
	{
		if (this.isDroppable())
		{
			jsDD.disableDest(this.getBody());
		}
	},

	enableDropping: function()
	{
		if (this.isDroppable())
		{
			jsDD.enableDest(this.getBody());
		}
	},

	/**
	 *
	 * @returns {boolean}
	 */
	isDraggable: function()
	{
		return this.isSortable();
	},

	/**
	 *
	 * @returns {boolean}
	 */
	isDroppable: function()
	{
		return this.droppable;
	},

	/**
	 *
	 * @param {Element} itemNode
	 * @param {number} x
	 * @param {number} y
	 */
	onDragEnter: function(itemNode, x, y)
	{
		var draggableItem = this.getGrid().getItemByElement(itemNode);
		this.showDragTarget(draggableItem.getBodyContainer().offsetHeight);
	},

	/**
	 *
	 * @param {Element} itemNode
	 * @param {number} x
	 * @param {number} y
	 */
	onDragLeave: function(itemNode, x, y)
	{
		this.hideDragTarget();
	},

	/**
	 *
	 * @param {Element} itemNode
	 * @param {number} x
	 * @param {number} y
	 */
	onDragDrop: function(itemNode, x, y)
	{
		this.hideDragTarget();
		var draggableItem = this.getGrid().getItemByElement(itemNode);

		var success = this.getGrid().moveItem(draggableItem, this);
		if (success)
		{
			BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onItemMoved", [draggableItem, this, null]);
		}
	},

	/**
	 *
	 * @param {Element} itemNode
	 * @param {number} x
	 * @param {number} y
	 */
	onItemDragEnd: function(itemNode, x, y)
	{
		this.disableDropping();
	},

	onColumnDragStart: function()
	{
		BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onColumnDragStart", [this]);

		this.getContainer().classList.add("main-kanban-column-draggable");

		this.dragColumnOffset = jsDD.start_x - this.getRectArea().left;
		this.dragColumnIndex = this.getIndex();
		this.dragTargetColumn = this.dragTargetColumn || this;
	},

	/**
	 *
	 * @param {number} x
	 * @param {number} y
	 */
	onColumnDrag: function(x, y)
	{
		this.getContainer().style.transform = "translateX(" + (x - this.dragColumnOffset - this.getRectArea().left) + "px)";

		var columns = this.getGrid().getColumns();
		var columnWidth = this.getRectArea().width;

		for (var columnIndex in columns)
		{
			var column = columns[columnIndex];
			if (column === this)
			{
				continue;
			}

			var columnContainer = column.getContainer();
			var columnRectArea = column.getRectArea();
			var columnMiddle = columnRectArea.middle;

			if (
				x > columnMiddle &&
				columnIndex > this.dragColumnIndex &&
				columnContainer.style.transform !== "translateX(" + (-columnWidth) + "px)"
			)
			{
				//move left
				columnContainer.style.transition = "300ms";
				columnContainer.style.transform = "translateX(" + (-columnWidth) + "px)";
				this.dragTargetColumn = this.getGrid().getNextColumnSibling(column);

				column.resetRectArea();
			}

			if (
				x < columnMiddle &&
				columnIndex < this.dragColumnIndex &&
				columnContainer.style.transform !== "translateX("+(columnWidth)+"px)"
			)
			{
				//move right
				columnContainer.style.transition = "300ms";
				columnContainer.style.transform = "translateX(" + columnWidth + "px)";
				this.dragTargetColumn = column;

				column.resetRectArea();
			}

			var moveBackRight =
				x < columnMiddle &&
				columnIndex > this.dragColumnIndex &&
				columnContainer.style.transform !== "" &&
				columnContainer.style.transform !== "translateX(0px)"
			;

			var moveBackLeft =
				x > columnMiddle &&
				columnIndex < this.dragColumnIndex &&
				columnContainer.style.transform !== "" &&
				columnContainer.style.transform !== "translateX(0px)"
			;

			if (moveBackLeft || moveBackRight)
			{
				//move to the start position
				columnContainer.style.transition = "300ms";
				columnContainer.style.transform = "translateX(0px)";
				this.dragTargetColumn = moveBackRight ? column : this.getGrid().getNextColumnSibling(column);

				column.resetRectArea();
			}

		}
	},

	/**
	 *
	 * @param {number} x
	 * @param {number} y
	 */
	onColumnDragStop: function(x, y)
	{
		BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onColumnDragStop", [this]);

		var success = this.getGrid().moveColumn(this, this.dragTargetColumn);
		if (success)
		{
			BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onColumnMoved", [this, this.getGrid().getNextColumnSibling(this)]);
		}

		this.getContainer().classList.remove("main-kanban-column-draggable");

		var columns = this.getGrid().getColumns();
		for (var columnIndex in columns)
		{
			var column = columns[columnIndex];
			var columnContainer = column.getContainer();

			column.resetRectArea();
			columnContainer.style.removeProperty("transition");
			columnContainer.style.removeProperty("transform");
		}
	},

	/**
	 *
	 * @returns {ClientRect}
	 */
	getRectArea: function()
	{
		if (!this.rectArea)
		{
			this.rectArea = BX.pos(this.getContainer());
			this.rectArea.middle = this.rectArea.left + this.rectArea.width / 2;
		}

		return this.rectArea;
	},

	resetRectArea: function()
	{
		this.rectArea = null;
	},

	/**
	 *
	 * @param {number} height
	 */
	showDragTarget: function(height)
	{
		this.getContainer().classList.add("main-kanban-column-target-shown");
		this.getDragTarget().style.height = height + "px";
	},

	hideDragTarget: function()
	{
		this.getContainer().classList.remove("main-kanban-column-target-shown");
		this.getDragTarget().style.removeProperty("height");
	}
};


/**
 *
 * @param options
 * @extends {BX.Kanban.Column}
 * @constructor
 */
BX.Kanban.DraftColumn = function(options)
{
	BX.Kanban.Column.apply(this, arguments);
	this.asyncEventStarted = false;

	BX.addCustomEvent(this, "Kanban.Column:onAddedToGrid", this.onAddedToGrid.bind(this));
};

BX.Kanban.DraftColumn.lastColorIndex = -1;

BX.Kanban.DraftColumn.prototype = {
	__proto__: BX.Kanban.Column.prototype,
	constructor: BX.Kanban.DraftColumn,

	applyEditMode: function()
	{
		if (this.asyncEventStarted)
		{
			return;
		}

		var title = BX.util.trim(this.getTitleTextBox().value);
		if (!title.length)
		{
			title = BX.message("MAIN_KANBAN_TITLE_PLACEHOLDER");
		}

		this.setName(title);
		this.getContainer().classList.add("main-kanban-column-disabled");
		this.getTitleTextBox().disabled = true;

		this.asyncEventStarted = true;
		var promise = this.getGrid().getEventPromise(
			"Kanban.Grid:onColumnAddedAsync",
			null,
			function(result) {

				if (!BX.Kanban.Utils.isValidId(result.targetId))
				{
					var targetColumn = this.getGrid().getNextColumnSibling(this);
					if (targetColumn)
					{
						result.targetId = targetColumn.getId();
					}
				}

				this.getGrid().removeColumn(this);
				this.getGrid().addColumn(result);

			}.bind(this),
			function(error) {

				this.getGrid().removeColumn(this);

			}.bind(this)
		);

		promise.fulfill(this);
	},

	handleRemoveButtonClick: function(event)
	{
		this.stopTextBoxBlur();
		this.getGrid().removeColumn(this);
	},

	onAddedToGrid: function()
	{
		this.setColor(this.getNextColor());
	},

	getNextColor: function()
	{
		var defaultColors = BX.Kanban.Utils.getDefaultColors();
		if (!defaultColors.length)
		{
			return null;
		}

		if (BX.Kanban.DraftColumn.lastColorIndex === -1)
		{
			var columns = this.getGrid().getColumns();
			for (var i = columns.length - 1; i >= 0; i--)
			{
				var column = columns[i];
				var index = BX.util.array_search(column.getColor(), defaultColors);
				if (index !== -1)
				{
					BX.Kanban.DraftColumn.lastColorIndex = index;
					break;
				}
			}
		}

		BX.Kanban.DraftColumn.lastColorIndex =
			defaultColors[BX.Kanban.DraftColumn.lastColorIndex + 1] ? BX.Kanban.DraftColumn.lastColorIndex + 1 : 0;

		return defaultColors[BX.Kanban.DraftColumn.lastColorIndex];
	}
};

/**
 *
 * @param {BX.Kanban.Column} column
 * @constructor
 */
BX.Kanban.Pagination = function(column)
{
	/** @var {BX.Kanban.Column} **/
	this.column = column;
	this.timer = null;
	this.page = 1;
	this.loadingInProgress = false;

	this.layout = {
		topButton: null,
		bottomButton: null,
		loader: null
	};

	BX.addCustomEvent(column, "Kanban.Column:onAddedToGrid", this.init.bind(this));
};

BX.Kanban.Pagination.prototype = {

	init: function()
	{
		var column = this.getColumn();
		var columnContainer = column.getContainer();
		var bodyContainer = column.getBody();
		columnContainer.appendChild(this.getTopButton());
		columnContainer.appendChild(this.getBottomButton());
		bodyContainer.appendChild(this.getLoader());

		var adjust = BX.delegate(this.adjust, this);

		BX.bind(bodyContainer, "scroll", BX.throttle(adjust, 150));
		BX.bind(window, "scroll", BX.throttle(adjust, 150));
		BX.addCustomEvent("Kanban.Grid:onFirstRender", adjust);
	},

	adjust: function()
	{
		var column = this.getColumn();
		var columnContainer = column.getContainer();
		var bodyContainer = column.getBody();

		var scrollHeight = bodyContainer.scrollHeight;
		var offsetHeight = bodyContainer.offsetHeight;
		var scrollTop = bodyContainer.scrollTop;

		var isTopVisible = bodyContainer.scrollTop > 0;
		var isBottomVisible = scrollHeight > offsetHeight + scrollTop;

		columnContainer.classList[isTopVisible ? "add" : "remove"]("main-kanban-column-top-button-shown");
		columnContainer.classList[isBottomVisible ? "add" : "remove"]("main-kanban-column-bottom-button-shown");

		if (columnContainer.classList.contains("main-kanban-column-top-button-shown"))
		{
			this.getTopButton().style.top = this.getColumn().getBody().offsetTop + "px";
		}

		var loader = this.getLoader();
		if (!this.loadingInProgress && column.hasLoading() && loader.offsetTop < scrollTop + offsetHeight)
		{
			this.showLoader();
			this.loadItems();
		}
	},

	loadItems: function()
	{
		this.loadingInProgress = true;

		var promise = this.getColumn().getGrid().getEventPromise(
			"Kanban.Grid:onColumnLoadAsync",
			null,
			this.onPromiseFulfilled.bind(this),
			this.onPromiseRejected.bind(this)
		);

		promise.fulfill(this.getColumn());
	},

	onPromiseFulfilled: function(result)
	{
		this.hideLoader();
		this.processPromiseResult(result);
	},

	onPromiseRejected: function(reason)
	{
		this.hideLoader();
		//this.loadingInProgress = false;
	},

	processPromiseResult: function(result)
	{
		if (!BX.type.isArray(result) || !result.length)
		{
			return;
		}

		var column = this.getColumn();
		var grid = column.getGrid();
		column.freezeTotal();

		for (var i = 0; i < result.length; i++)
		{
			var item = result[i];
			grid.addItem(item);
		}

		column.unfreezeTotal();
		column.refreshTotal();

		this.page++;
		this.loadingInProgress = false;

		this.adjust();
	},

	/**
	 *
	 * @returns {BX.Kanban.Column}
	 */
	getColumn: function()
	{
		return this.column;
	},

	/**
	 *
	 * @returns {number}
	 */
	getPage: function()
	{
		return this.page;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getTopButton: function()
	{
		if (this.layout.topButton)
		{
			return this.layout.topButton;
		}

		this.layout.topButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-top-button"
			},
			events: {
				mouseenter: BX.delegate(this.scrollUp, this),
				mouseleave: BX.delegate(this.stopScroll, this)
			}
		});

		return this.layout.topButton;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getBottomButton: function()
	{
		if (this.layout.bottomButton)
		{
			return this.layout.bottomButton;
		}

		this.layout.bottomButton = BX.create("div", {
			attrs: {
				className: "main-kanban-column-bottom-button"
			},
			events: {
				mouseenter: BX.delegate(this.scrollDown, this),
				mouseleave: BX.delegate(this.stopScroll, this)
			}
		});

		return this.layout.bottomButton;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getLoader: function()
	{
		if (this.layout.loader)
		{
			return this.layout.loader;
		}

		this.layout.loader = BX.create("div", {
			attrs: {
				className: "main-kanban-loader"
			},
			html:
				'<div class="main-kanban-loader-outer">' +
					'<div class="main-kanban-loader-inner">' +
						'<svg class="main-kanban-loader-circle" viewBox="25 25 50 50">' +
							'<circle ' +
								'class="main-kanban-loader-path" cx="50" cy="50" r="20" fill="none" ' +
								'stroke-width="1" stroke-miterlimit="10"' +
							'/>' +
						'</svg>' +
					'</div>' +
				'</div>'
		});

		return this.layout.loader;
	},

	showLoader: function()
	{
		this.getLoader().classList.add("main-kanban-loader-shown");
	},

	hideLoader: function()
	{
		this.getLoader().classList.remove("main-kanban-loader-shown");
	},

	scrollUp: function()
	{
		if (this.getColumn().getGrid().getDragMode() !== BX.Kanban.DragMode.ITEM)
		{
			return;
		}

		this.timer = setInterval(BX.delegate(function()
		{
			this.getColumn().getBody().scrollTop -= 10;
			jsDD.refreshDestArea();
		}, this), 20);
	},

	scrollDown: function()
	{
		if (this.getColumn().getGrid().getDragMode() !== BX.Kanban.DragMode.ITEM)
		{
			return;
		}

		this.timer = setInterval(BX.delegate(function()
		{
			this.getColumn().getBody().scrollTop += 10;
			jsDD.refreshDestArea();
		}, this), 20);
	},

	stopScroll: function()
	{
		clearInterval(this.timer);
	}
};

})();