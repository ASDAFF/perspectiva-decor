;(function() {

"use strict";

BX.namespace("BX.Kanban");

/**
 *
 * @param {object} options
 * @param {string|number} options.id
 * @param {object} options.data
 * @constructor
 */
BX.Kanban.Item = function(options)
{
	if (!BX.type.isPlainObject(options))
	{
		throw new Error("BX.Kanban.Item: 'options' is not an object.");
	}

	this.options = options;

	if (!BX.Kanban.Utils.isValidId(options.id))
	{
		throw new Error("BX.Kanban.Item: 'id' parameter is not valid.")
	}

	this.id = options.id;

	/** @var {BX.Kanban.Grid} **/
	this.grid = null;

	this.columnId = null;

	this.layout = {
		container: null,
		dragTarget: null,
		bodyContainer: null
	};

	/** @var {Element} **/
	this.dragElement = null;
	this.draggable = true;
	this.droppable = true;

	this.countable = true;

	this.data = Object.create(null);

	this.setOptions(options);
};

BX.Kanban.Item.prototype =
{
	/**
	 *
	 * @returns {number|string}
	 */
	getId: function()
	{
		return this.id;
	},

	/**
	 *
	 * @returns {number|string}
	 */
	getColumnId: function()
	{
		return this.columnId;
	},

	setColumnId: function(columnId)
	{
		this.columnId = columnId;
	},

	/**
	 *
	 * @returns {BX.Kanban.Column|null}
	 */
	getColumn: function()
	{
		if (this.getGrid())
		{
			return this.getGrid().getColumn(this.getColumnId());
		}

		return null;
	},

	/**
	 * @param {BX.Kanban.Grid} grid
	 */
	setGrid: function(grid)
	{
		if (grid instanceof BX.Kanban.Grid)
		{
			this.grid = grid;
		}
	},

	/**
	 * @returns {BX.Kanban.Grid}
	 */
	getGrid: function()
	{
		return this.grid;
	},

	setOptions: function(options)
	{
		if (!options)
		{
			return;
		}

		this.setData(options.data);
		this.droppable = BX.type.isBoolean(options.droppable) ? options.droppable : this.droppable;
		this.draggable = BX.type.isBoolean(options.draggable) ? options.draggable : this.draggable;
		this.countable = BX.type.isBoolean(options.countable) ? options.countable : this.countable;
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

	isCountable: function()
	{
		return this.countable;
	},

	getGridData: function()
	{
		return this.getGrid().getData();
	},

	/**
	 *
	 * @returns {Element}
	 */
	renderLayout: function()
	{
		var bodyContainer = this.getBodyContainer();
		BX.cleanNode(bodyContainer);
		bodyContainer.appendChild(this.render());
		return this.getContainer();
	},

	/**
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
				className: "main-kanban-item",
				"data-id": this.getId(),
				"data-type": "item"
			},
			children: [
				this.getDragTarget(),
				this.getBodyContainer()
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
	getDragTarget: function()
	{
		if (!this.layout.dragTarget)
		{
			this.layout.dragTarget = BX.create("div", {
				attrs: {
					className: "main-kanban-item-drag-target"
				}
			});
		}

		return this.layout.dragTarget;
	},

	/**
	 *
	 * @returns {Element}
	 */
	getDragElement: function()
	{
		return this.dragElement;
	},

	getBodyContainer: function()
	{
		if (!this.layout.bodyContainer)
		{
			this.layout.bodyContainer = BX.create("div", {
				attrs: {
					className: "main-kanban-item-wrapper"
				}
			});
		}

		return this.layout.bodyContainer;
	},

	/**
	 * Default Item Render
	 *
	 * @returns {Element}
	 */
	render: function()
	{
		if (!this.layout.content)
		{
			this.layout.content = BX.create("div", {
				props: {
					className: "main-kanban-item-default"
				}
			});
		}

		this.layout.content.style.borderLeft = "2px solid #" + this.getColumn().getColor();
		this.layout.content.textContent = "#" + this.getId();

		return this.layout.content;
	},

	makeDraggable: function()
	{
		if (!this.isDraggable())
		{
			return;
		}

		var itemContainer = this.getContainer();

		//main events
		itemContainer.onbxdragstart = BX.delegate(this.onDragStart, this);
		itemContainer.onbxdrag = BX.delegate(this.onDrag, this);
		itemContainer.onbxdragstop = BX.delegate(this.onDragStop, this);

		jsDD.registerObject(itemContainer);
	},

	makeDroppable: function()
	{
		if (!this.isDroppable())
		{
			return;
		}

		var itemContainer = this.getContainer();

		itemContainer.onbxdestdraghover = BX.delegate(this.onDragEnter, this);
		itemContainer.onbxdestdraghout = BX.delegate(this.onDragLeave, this);
		itemContainer.onbxdestdragfinish = BX.delegate(this.onDragDrop, this);

		itemContainer.onbxdestdragstop = BX.delegate(this.onItemDragEnd, this);

		jsDD.registerDest(itemContainer, 30);

		if (this.getGrid().getDragMode() !== BX.Kanban.DragMode.ITEM)
		{
			//when we load new items in drag mode
			this.disableDropping();
		}
	},

	disableDragging: function()
	{
		if (this.isDraggable())
		{
			jsDD.unregisterObject(this.getContainer());
		}
	},

	enableDragging: function()
	{
		if (this.isDraggable())
		{
			jsDD.registerObject(this.getContainer());
		}
	},

	disableDropping: function()
	{
		if (this.isDroppable())
		{
			jsDD.disableDest(this.getContainer());
		}
	},

	enableDropping: function()
	{
		if (this.isDroppable())
		{
			jsDD.enableDest(this.getContainer());
		}
	},

	/**
	 *
	 * @returns {boolean}
	 */
	isDraggable: function()
	{
		return this.draggable && this.getGrid().canSortItems();
	},

	/**
	 *
	 * @returns {boolean}
	 */
	isDroppable: function()
	{
		return this.droppable;
	},

	onDragStart: function()
	{
		this.getContainer().classList.add("main-kanban-item-disabled");

		if (!this.dragElement)
		{
			var itemContainer = this.getContainer();
			var bodyContainer = this.getBodyContainer();

			this.dragElement = itemContainer.cloneNode(true);

			this.dragElement.style.position = "absolute";
			this.dragElement.style.width = bodyContainer.offsetWidth + "px";
			this.dragElement.className = "main-kanban-item main-kanban-item-drag";

			document.body.appendChild(this.dragElement);
		}

		BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onItemDragStart", [this]);
	},

	/**
	 *
	 * @param {number} x
	 * @param {number} y
	 */
	onDragStop: function(x, y)
	{
		BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onItemDragStop", [this]);

		this.getContainer().classList.remove("main-kanban-item-disabled");
		BX.remove(this.dragElement);
		this.dragElement = null;
	},

	/**
	 *
	 * @param {number} x
	 * @param {number} y
	 */
	onDrag: function(x, y)
	{
		if (this.dragElement)
		{
			this.dragElement.style.left = x + "px";
			this.dragElement.style.top = y + "px";
		}
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
		if (draggableItem !== this)
		{
			this.showDragTarget(draggableItem.getBodyContainer().offsetHeight);
		}
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

		BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onItemDragDrop", [draggableItem, this.getColumn(), this]);

		var success = this.getGrid().moveItem(draggableItem, this.getColumn(), this);
		if (success)
		{
			BX.onCustomEvent(this.getGrid(), "Kanban.Grid:onItemMoved", [draggableItem, this.getColumn(), this]);
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

	/**
	 *
	 * @param {number} height
	 */
	showDragTarget: function(height)
	{
		this.getContainer().classList.add("main-kanban-item-target-shown");
		this.getDragTarget().style.height = height + "px";
	},

	hideDragTarget: function()
	{
		this.getContainer().classList.remove("main-kanban-item-target-shown");
		this.getDragTarget().style.removeProperty("height");
	}

};


/**
 *
 * @param options
 * @extends {BX.Kanban.Item}
 * @constructor
 */
BX.Kanban.DraftItem = function(options)
{
	BX.Kanban.Item.apply(this, arguments);
	this.asyncEventStarted = false;
	this.draftContainer = null;
	this.draftTextArea = null;
};

/**
 *
 * @param {BX.Kanban.Column} column
 * @param {BX.Kanban.Item} [targetItem]
 * @returns {BX.Kanban.DraftItem|null}
 * @static
 */
BX.Kanban.DraftItem.addToColumn = function(column, targetItem)
{
	if (!column instanceof BX.Kanban.Column)
	{
		return null;
	}

	var id = "kanban-new-item-" + column.getId();
	if (column.getGrid().getItem(id))
	{
		return null;
	}

	var targetId = null;
	if (targetItem instanceof BX.Kanban.Item && targetItem.getColumn() === column)
	{
		targetId = targetItem;
	}

	var newItem = column.getGrid().addItem({
		id: id,
		type: "BX.Kanban.DraftItem",
		columnId: column.getId(),
		draggable: false,
		droppable: false,
		countable: false,
		targetId: targetId
	});

	if (newItem)
	{
		newItem.focusDraftTextArea();
	}

	return newItem;
};

BX.Kanban.DraftItem.prototype = {
	__proto__: BX.Kanban.Item.prototype,
	constructor: BX.Kanban.DraftItem,

	/**
	 * @override
	 */
	render: function()
	{
		if (this.draftContainer)
		{
			return this.draftContainer;
		}

		this.draftContainer = BX.create("div", {
			props: {
				className: "main-kanban-item-draft"
			},
			children: [
				this.getDraftTextArea()
			]
		});

		return this.draftContainer;
	},

	/**
	 * @inheritDoc
	 * @override
	 * @param {BX.Kanban.Grid} grid
	 */
	setGrid: function(grid)
	{
		BX.Kanban.Item.prototype.setGrid.apply(this, arguments);
		BX.addCustomEvent(this.getGrid(), "Kanban.Grid:onItemDragStart", BX.proxy(this.applyDraftEditMode, this));
	},

	/**
	 *
	 * @returns {Element}
	 */
	getDraftTextArea: function()
	{
		if (this.draftTextArea)
		{
			return this.draftTextArea;
		}

		this.draftTextArea = BX.create("textarea", {
			attrs: {
				className: "main-kanban-item-draft-textarea",
				placeholder: BX.message("MAIN_KANBAN_TITLE_PLACEHOLDER")
			},
			events: {
				blur: this.handleDraftTextAreaBlur.bind(this),
				keydown: this.handleDraftTextAreaKeyDown.bind(this)
			}
		});

		return this.draftTextArea;
	},

	applyDraftEditMode: function()
	{
		if (this.asyncEventStarted)
		{
			return;
		}

		this.asyncEventStarted = true;

		var title = BX.util.trim(this.getDraftTextArea().value);
		if (!title.length)
		{
			this.removeDraftItem();
			return;
		}

		this.setData({ title: title });
		this.getContainer().classList.add("main-kanban-item-draft-disabled");
		this.getDraftTextArea().disabled = true;

		var promise = this.getGrid().getEventPromise(
			"Kanban.Grid:onItemAddedAsync",
			null,
			this.onItemAddedFulfilled.bind(this),
			this.onItemAddedRejected.bind(this)
		);

		promise.fulfill(this);
	},

	onItemAddedFulfilled: function(result)
	{
		if (!BX.type.isPlainObject(result))
		{
			this.removeDraftItem();
			return;
		}

		if (!BX.Kanban.Utils.isValidId(result.targetId))
		{
			var targetItem = this.getColumn().getNextItemSibling(this);
			if (targetItem)
			{
				result.targetId = targetItem.getId();
			}
		}

		this.removeDraftItem();
		var newItem = this.getGrid().addItem(result);
		if (newItem && this.getGrid().getDragMode() === BX.Kanban.DragMode.NONE)
		{
			var nextItem = newItem.getColumn().getNextItemSibling(newItem);
			BX.Kanban.DraftItem.addToColumn(newItem.getColumn(), nextItem);
		}
	},

	onItemAddedRejected: function(error)
	{
		this.removeDraftItem();
	},

	removeDraftItem: function()
	{
		this.asyncEventStarted = true;
		BX.removeCustomEvent(this.getGrid(), "Kanban.Grid:onItemDragStart", BX.proxy(this.applyDraftEditMode, this));
		this.getGrid().removeItem(this);
	},

	focusDraftTextArea: function()
	{
		this.getDraftTextArea().focus();
	},

	handleDraftTextAreaBlur: function(event)
	{
		//Blur event can be fired when `render` cleans items container.
		//It causes DOM exception.
		setTimeout(function() {
			this.applyDraftEditMode();
		}.bind(this), 0);
	},

	handleDraftTextAreaKeyDown: function(event)
	{
		if (event.keyCode === 13)
		{
			this.applyDraftEditMode();
		}
		else if (event.keyCode === 27)
		{
			this.removeDraftItem();
		}
	}
};


})();