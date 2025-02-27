;(function(){
	if (!window["BX"] || window["BX"]["MPF"] || !window["app"])
		return;

	var repo = {},
		fileObj = function(uri) {
			if (BX.type.isNotEmptyString(uri))
			{
				this.id = uri;
				this.url = uri;
				this.name = uri.substr(uri.lastIndexOf('/') + 1);
				if (this.name.indexOf("?") >= 0)
					this.name = this.name.substr(0, this.name.indexOf("?"));
				this.ext = (this.name.lastIndexOf('.') > 0 ? this.name.substr(this.name.lastIndexOf('.') + 1).toLowerCase() : "");
			}
			else
			{
				for (var ii in uri)
				{
					if (uri.hasOwnProperty(ii))
					{
						this[ii] = uri[ii];
					}
				}
			}
		},

		diskController = function(manager, id, params) {
			this.id = id;
			this.url = window.location.protocol + '//' + window.location.host + this.urlUpload;
			this.values = {};
			this.params = params;

			this.propertyName = this.params["FIELD_NAME"];

			this.handleBase64 = BX.delegate(this.handleBase64, this);
			this.catchUF = BX.delegate(this.catchUF, this);
			this.parseUF = BX.delegate(this.parseUF, this);
			this.prepareToSaveUF = BX.delegate(this.prepareToSaveUF, this);
		};
	fileObj.prototype = {
		getErrorText : function(text) {
			return (text || BX.message("MPFFileWasNotUploaded"));
		}
	};
	diskController.prototype = {
		prefixHTMLNode : 'disk-attach-',
		userTypeId : 'disk_file',
		urlUpload : '/bitrix/tools/disk/uf.php?action=uploadFile&ncc=1',
		handleBase64 : function(fileObj, result) {
			result.filesToPost = true;
			BX.addCustomEvent(fileObj, "onUploadStart", BX.proxy(this.uploadBase64, this));
		},
		uploadBase64 : function(fileObj) {
			var options = new window.FileUploadOptions(),
				ft = new window.FileTransfer(),
				good = BX.proxy(function (response) {
					response = BX.parseJSON(response.response);
					if (response == null)
						bad();
					else
						this.uploadBase64Response(fileObj, response);
				}, this),
				bad = BX.proxy(function () {
					this.uploadBase64Failure(fileObj, BX.message("MPFIncorrectResponse"));
				}, this);

			options.fileKey = this.userTypeId;
			options.fileName = fileObj.name;
			options.params = { sessid: BX.bitrix_sessid() };
			options.mimeType = "image/jpeg";
			options.chunkedMode = false;

			ft.upload(fileObj.url, this.url,
				good,
				BX.proxy(function()
				{
					window.app.BasicAuth({
						'success': BX.proxy(function(auth_data)
						{
							options.params.sessid = auth_data.sessid_md5;
							ft.upload(
								fileObj.url,
								this.url,
								good,
								bad,
								options
							);
						}, this),
						failure : bad
					});
				}, this), options);
		},
		uploadBase64Failure : function(fileObj, text) {
			BX.onCustomEvent(fileObj, "onUploadError", [fileObj.getErrorText(text)]);
		},
		uploadBase64Response : function(fileObj, response) {
			var text;
			if (response.status != 'success')
			{
				text = response['message'];
				if (!text && BX.type.isArray(response["errors"]))
				{
					for (var ii = 0; ii < response["errors"].length; ii++)
					{
						if (response["errors"][ii] && response["errors"][ii]["message"])
						{
							text = (text || '') + response["errors"][ii]["message"];
						}

					}
				}
				this.uploadBase64Failure(fileObj, text);
			}
			else
			{
				response = response.data;
				var id = (response["attachId"] || response["id"]), iconUrl = "blank";
				if (BX.util.in_array(fileObj.ext, ["jpg", "bmp", "jpeg", "jpe", "gif", "png"]))
					iconUrl = "img";
				else if (BX.util.in_array(fileObj.ext, ["doc", "pdf", "ppt", "rar", "xls", "zip"]))
					iconUrl = fileObj.ext;

				BX.onCustomEvent(fileObj, 'onUploadOk', ['[DISK FILE ID=' + id + ']', {
					extension: response["ext"],
					iconUrl: "/bitrix/components/bitrix/mobile.disk.file.detail/images/" + iconUrl + ".png",
					previewImageUrl : '',
					id: id,
					fileId : id,
					xmlID : "0",
					name: response["name"],
					type: response["ext"],
					propertyName : this.propertyName,
					fieldName : this.propertyName + (this.params["MULTIPLE"] == "Y" ? "[]" : ""),
					fieldValue : id,
					url: (BX.message('MobileSiteDir') || '/') + "mobile/ajax.php?attachedId=" + id + "&action=download&ncc=1&mobile_action=disk_uf_view&filename=" + ["name"]
				}, this]);
			}
		},
		catchUF : function(UF, files, extraData) {

			if (UF && UF[this.propertyName] && UF[this.propertyName]["USER_TYPE_ID"] == this.userTypeId && BX.type.isArray(UF[this.propertyName]["VALUE"]))
			{
				extraData["uf"] = (extraData["uf"] || {});
				UF = UF[this.propertyName];
				var getId = function() {
					var tempId = 'id' + Math.random();
					while (extraData["uf"][tempId])
						tempId = 'id' + Math.random();
					return tempId;
				};
				for (var ii = 0, node, id, name, ext, iconUrl, data, tempId; ii < UF["VALUE"].length; ii++)
				{
					id = UF["VALUE"][ii];
					node = BX(this.prefixHTMLNode + id);
					name = (node.getAttribute("data-bx-title") || "noname");
					ext = (name.lastIndexOf('.') > 0 ? name.substr(name.lastIndexOf('.') + 1).toLowerCase() : "");
					iconUrl = "blank";
					tempId = getId();

					if (BX.util.in_array(ext, ["jpg", "bmp", "jpeg", "jpe", "gif", "png"]))
						iconUrl = "img";
					else if (BX.util.in_array(ext, ["doc", "pdf", "ppt", "rar", "xls", "zip"]))
						iconUrl = ext;

					if (node)
					{
						data = {
							extension: ext,
							iconUrl: "/bitrix/components/bitrix/mobile.disk.file.detail/images/" + iconUrl + ".png",
							previewImageUrl : (node.getAttribute("data-bx-src") || node.getAttribute("src") || undefined),
							id: tempId,
							fileId : node.getAttribute("bx-attach-file-id"),
							xmlID : node.getAttribute("bx-attach-xml-id"),
							name: name,
							type: ext,
							propertyName : this.propertyName,
							fieldName : this.propertyName + (this.params["MULTIPLE"] == "Y" ? "[]" : ""),
							fieldValue : id,
							url: (BX.message('MobileSiteDir') || '/') + "mobile/ajax.php?attachedId=" + id + "&action=download&ncc=1&mobile_action=disk_uf_view&filename=" + name
						};
						extraData["uf"][tempId] = data;
						files.push(data);
					}
				}
			}
		},
		parseUF : function(data, files) {
			if (files && files.length > 0)
			{
				var text = data.text, ii, file;
				for (ii = 0; ii < files.length; ii++)
				{
					file = files[ii];
					if (file.propertyName == this.propertyName && parseInt(file.fileId) > 0)
					{
						text = text.replace("[DISK FILE ID=n" + file.fileId + "]", "[DISK FILE ID=" + file.id + "]");
					}
				}
				data.text = text;
			}
		},
		prepareToSaveUF : function(attachments, queue) {
			if (attachments.length > 0)
			{
				var ii,
					file,
					files = [];

				for (ii = 0; ii < attachments.length; ii++)
				{
					file = attachments[ii];
					if (!file["propertyName"] && file["base64"]) // I am sorry
					{
						file["propertyName"] = this.propertyName;
						files.push(file);
					}
					else if (!file["propertyName"] && file["VALUE"]) // I am sorry
					{
						file["name"] = file["NAME"];
						file["ext"] = (file["name"].lastIndexOf('.') > 0 ? file["name"].substr(file["name"].lastIndexOf('.') + 1).toLowerCase() : "");
						file["id"] = file["ID"];
						file["fileId"] = file["ID"];
						file["xmlID"] = 0;
						file["type"] = file["ext"];
						file["propertyName"] = this.propertyName;
						file["fieldName"] = this.propertyName + (this.params["MULTIPLE"] == "Y" ? "[]" : "");
						file["fieldValue"] = file["VALUE"];
						file["url"] = file["URL"]["URL"];
					}
					else if (!file["propertyName"] && file["dataAttributes"] && file["dataAttributes"]["VALUE"]) // I am sorry
					{
						var f = file["dataAttributes"];
						file["name"] = f["NAME"];
						file["ext"] = (file["name"].lastIndexOf('.') > 0 ? file["name"].substr(file["name"].lastIndexOf('.') + 1).toLowerCase() : "");
						file["id"] = f["ID"];
						file["fileId"] = f["ID"];
						file["xmlID"] = 0;
						file["type"] = file["ext"];
						file["propertyName"] = this.propertyName;
						file["fieldName"] = this.propertyName + (this.params["MULTIPLE"] == "Y" ? "[]" : "");
						file["fieldValue"] = f["VALUE"];
						file["url"] = f["URL"]["URL"];
					}
				}

				if (files.length > 0)
				{
					queue.add(this, files);
				}
			}
			else
			{
				attachments.push({
					fieldName : this.propertyName + (this.params["MULTIPLE"] == "Y" ? "[]" : ""),
					fieldValue : ""
				});
			}
		},
		upload : function(files) {
			var file = files.pop();
			if (file)
			{
				var f0 = BX.proxy(function(text, fileD){
						BX.removeCustomEvent(file, "onUploadOk", f0);
						BX.removeCustomEvent(file, "onUploadError", f1);
						for (var ii in fileD)
						{
							if (fileD.hasOwnProperty(ii))
							{
								file[ii] = fileD[ii];
							}
						}
						this.upload(files);
					}, this),
					f1 = BX.proxy(function(text){
						BX.removeCustomEvent(file, "onUploadOk", f0);
						BX.removeCustomEvent(file, "onUploadError", f1);
						BX.onCustomEvent(this, "onUploadError", [text]);
					}, this);
				BX.addCustomEvent(file, "onUploadOk", f0);
				BX.addCustomEvent(file, "onUploadError", f1);
				this.uploadBase64(file);
				return;
			}
			BX.onCustomEvent(this, "onUploadOk", []);
		}
	};
	var uploadQueue = function() {
	};
	uploadQueue.prototype = {
		files : [],
		queue : {},
		getId : function() {
			return 'id' + Math.random();
		},
		add : function(controller, files) {
			if (!controller["__queueId"])
			{
				controller.__queueId = this.getId();
				controller.__onUploadOk = BX.delegate(function(){this.start(controller);}, this);
				controller.__onUploadError = BX.delegate(this.error, this);
				BX.addCustomEvent(controller, "onUploadOk", controller.__onUploadOk);
				BX.addCustomEvent(controller, "onUploadError", controller.__onUploadError);
			}
			else
			{
				var file, files1 = (this.queue[controller.__queueId] || [controller, []])[1];
				while ((file = files.pop()) && file)
				{
					files1.push(file);
				}
				files = files1;
			}
			this.queue[controller.__queueId] = [controller, files];
		},
		start : function(controller) {

			if (controller && controller.__queueId)
			{
				this.clear(controller);
			}
			var q;
			for (var ii in this.queue)
			{
				if (this.queue.hasOwnProperty(ii))
				{
					q = this.queue[ii];
					delete this.queue[ii];
					if (q[0] && q[0]["upload"])
					{
						q[0]["upload"](q[1]);
					}
					else
					{
						this.start(q[0]);
					}
					return;
				}
			}
			BX.onCustomEvent(this, "onUploadOk", []);
		},
		clear : function(controller)
		{
			if (controller.__queueId)
			{
				delete this.queue[controller.__queueId];
				delete controller.__queueId;
				BX.removeCustomEvent(controller, "onUploadOk", controller.__onUploadOk);
				BX.removeCustomEvent(controller, "onUploadError", controller.__onUploadError);
				delete controller.__onUploadOk;
				delete controller.__onUploadError;
			}
		},
		error : function() {
			var res = [], ii;
			for (ii in this.queue)
			{
				if (this.queue.hasOwnProperty(ii))
				{
					res.push(ii);
				}
			}
			while ((ii = res.pop()) && ii)
				this.clear(this.queue[ii]);

			BX.onCustomEvent(this, "onUploadError", [BX.message("MPFFileWasNotUploaded")]);
		},
		hasSmthToUpload : function() {
			for (var ii in this.queue)
			{
				if (this.queue.hasOwnProperty(ii))
				{
					return true;
				}
			}
			return false;
		}
	};
	var simpleForm = function(handler) {
		this.handler = handler;
		this.id = BX.util.getRandomString(8);
		this.params = {
			placeholder: BX.message("MPFPlaceholder"),
			button_name: BX.message("MPFButtonSend"),
			mentionDataSource: {
				outsection: false,
				url: (BX.message('MobileSiteDir') ? BX.message('MobileSiteDir') : '/') + "mobile/index.php?mobile_action=get_user_list&use_name_format=Y"
			},
			plusAction: BX.delegate(function()
			{
				(new window.BXMobileApp.UI.ActionSheet(
					{
						buttons: [
							{
								title: BX.message("MPFTakeAPhoto"),
								callback: BX.delegate(function()
								{
									window.app.takePhoto({
										source: 1,
										correctOrientation: true,
										targetWidth: 1000,
										targetHeight: 1000,
										callback: BX.delegate(this.handleAppFile, this)
									});
								}, this)
							},
							{
								title: BX.message("MPFSelectFromTheGallery"),
								callback: BX.delegate(function()
								{
									window.app.takePhoto({
										targetWidth: 1000,
										targetHeight: 1000,
										callback: BX.delegate(this.handleAppFile, this)
									});
								}, this)
							}
						]
					},
					"textPanelSheet"
				)).show();
			}, this),
			useImageButton: true,
			action: BX.delegate(this.handleAppData, this),
			callback: BX.delegate(this.handleAppCallback, this)
		};
	};
	simpleForm.prototype = {
		handleAppData : function(text, repeat) {
			if (!repeat)
			{
				this.handler.comment.node = null;
			}
			this.stopCheckWriting();
			BX.onCustomEvent(this, 'onFormSubmitted', [text]);
		},
		handleAppFile : function(uri, repeat) {
			if (!repeat)
			{
				this.handler.comment.node = null;
			}
			this.stopCheckWriting();
			var __this = this;
			window.BXMobileApp.UI.Page.TextPanel.getText(function(txt){
				BX.onCustomEvent(__this, 'onFileSubmitted', [txt, new fileObj(uri)]);
			});
		},
		handleAppCallback : function(e) {
			if (this.writingParams.lastEvent != e && (!e || e["event"] != "removeFocus"))
			{
				this.writingParams.lastEvent = e;
				this.writingParams.text += e.text;
				this.writingParams["~text"] = e.text;

				window.BXMobileApp.onCustomEvent("main.post.form/text", [e.text], true, true);

				if (this.writingParams.text.length > 4)
				{
					this.writingParams.text = '';
					this.startCheckWriting();
				}
			}
		},
		init : function(text) {
			text = (text || '');

			this.params.text = text;
			window.BXMobileApp.UI.Page.TextPanel.show(this.params);

			if (BX.type.isNotEmptyString(text))
			{
				//setTimeout(function(){ window.BXMobileApp.UI.Page.TextPanel.setText(text); }, 100);
				this.writingParams["~text"] = text;
			}
			else
			{
				//window.BXMobileApp.UI.Page.TextPanel.clear();
				this.writingParams["~text"] = '';
			}

			this.writingParams.text = '';
		},
		show : function(text) {
			if (BX.type.isString(text))
			{
				window.BXMobileApp.UI.Page.TextPanel.setText(text);
				this.writingParams["~text"] = text;
			}
			window.BXMobileApp.UI.Page.TextPanel.focus();
		},
		clear : function() {
			this.writingParams.text = '';
			this.writingParams["~text"] = '';
			window.BXMobileApp.UI.Page.TextPanel.clear();
		},
		writingParams : {
			lastFired : 0,
			lastEvent : null, // Because of mobile version bug
			frequency : 10000,
			text : '',
			'~text' : ''
		},
		stopCheckWriting : function(){
			this.writingParams.text = '';
		},
		startCheckWriting : function() {
			var time = new Date();

			if ((time - this.writingParams.lastFired) > this.writingParams.frequency)
			{
				BX.onCustomEvent(this, 'onUserIsWriting', [this]);
				this.writingParams.lastFired = time;
			}
		},
		showWait : function() {
			window.BXMobileApp.UI.Page.TextPanel.showLoading(true);
		},
		closeWait : function() {
			window.BXMobileApp.UI.Page.TextPanel.showLoading(false);
		}
	};
	var extendedForm = function(handler, params) {
		this.handler = handler;
		this.formSettings = {
			attachButton : { items : this.initFiles(params["CID"]) },
			attachFileSettings: {
				resize: [40, 1, 1, 1000, 1000, 0, 0, false, true, false, null, 0],
				sendLocalFileMethod: "base64",
				saveToPhotoAlbum: true
			},
			attachedFiles : [],
			extraData: {},
			mentionButton: {
				dataSource: {
					return_full_mode: "YES",
					outsection: "NO",
					okname: BX.message("MPFButtonSend"),
					cancelname: BX.message("MPFButtonCancel"),
					multiple: "NO",
					alphabet_index: "YES",
					url: BX.message('MobileSiteDir') + 'mobile/index.php?mobile_action=get_user_list'
				}
			},
			smileButton: {},
			message : {
				text : ""
			},
			okButton: {
				callback: BX.delegate(this.applyExtendedForm, this),
				name: BX.message("MPFButtonSend")
			},
			cancelButton : {
				callback : BX.delegate(this.cancelExtendedForm, this),
				name : BX.message("MPFButtonCancel")
			}
		};
	};

	extendedForm.prototype = {
		initFiles : function(controllers) {
			this.controllers = {
				/*
				common : {
					storage : "bfile",
					parser : "postimage",
					node : window,
					obj : null,
					init : false
				}
				*/
			};
			if (!controllers || typeof controllers !== "object")
				return [];

			var cid, buttons = [], button;
			for (cid in controllers)
			{
				if (controllers.hasOwnProperty(cid))
				{
					if (controllers[cid]["USER_TYPE_ID"] == "disk_file")
					{
						button = {
							id: "disk",
							name: BX.message('MPFPostFormDisk'),
							dataSource: {
								multiple: "NO",
								url: BX.message('SITE_DIR') + 'mobile/?mobile_action=disk_folder_list&type=user&path=%2F&entityId=' + BX.message('USER_ID')
							}
						};
						button.dataSource[
							(window["platform"] == "ios" ?
								"table_settings" :
								"TABLE_SETTINGS")
							] = {
							searchField: "YES",
							showtitle: "YES",
							modal: "YES",
							name: BX.message('MPFPostFormDiskTitle')
						};
						buttons.push(button);
					}
				}
			}
			if (buttons.length > 0)
			{
				buttons.push({
					id: "mediateka",
					name: BX.message('MPFPostFormPhotoGallery')
				});

				buttons.push({
					id: "camera",
					name: BX.message('MPFPostFormPhotoCamera')
				});
			}
			return buttons;
		},
		applyExtendedForm : function(data) {
			this.stopCheckWriting();
			data.text = (data.text || '');
			data.attachedFiles = (data.attachedFiles || []);
			for (var ii = 0; ii < data.attachedFiles.length; ii++)
			{
				data.attachedFiles[ii] = new fileObj(data.attachedFiles[ii]);
			}
			data.extraData = (data.extraData || {});
			BX.onCustomEvent(this, "onApplyComment", [data, data.attachedFiles]); // Service event for controllers
			BX.onCustomEvent(this, "onFormSubmitted", [data.text, data.attachedFiles, data.extraData]);
		},
		cancelExtendedForm : function() {
			this.stopCheckWriting();
		},
		show : function(text, attachments) {
			this.formSettings.message = {
				text: text
			};
			this.formSettings.attachedFiles = [];
			this.formSettings.extraData = {};
			if (attachments)
			{
				BX.onCustomEvent(this, "onEditCommentUF", [attachments["UF"], this.formSettings.attachedFiles, this.formSettings.extraData]);
				BX.onCustomEvent(this, "onEditCommentFiles", [attachments["FILES"], this.formSettings.attachedFiles, this.formSettings.extraData]);
			}

			window.app.exec('showPostForm', this.formSettings);
		},
		clear : function() {
			this.writingParams.text = '';
			this.writingParams["~text"] = '';
		},
		writingParams : {
			lastFired : 0,
			lastEvent : null, // Because of mobile version bug
			frequency : 10000,
			text : ''
		},
		stopCheckWriting : function(){
			this.writingParams.text = '';
		},
		startCheckWriting : function() {
			var time = new Date();

			if ((time - this.writingParams.lastFired) > this.writingParams.frequency)
			{
				//BX.onCustomEvent(this, 'onMPFUserIsWriting', [this]);
				this.writingParams.lastFired = time;
			}
		},
		showWait : function() {
		},
		closeWait : function() {
		}
	};

	BX.MPF = function(params)
	{
		if (!window.app.enableInVersion(4))
			throw this.errors["error00"];
		if (repo[params["formId"]])
			repo[params["formId"]].destroy();

		this.form = BX(params["formId"]);

		if (!this.form)
			throw this.errors["error01"];

		this.id = this.form.id;

		BX.hide(this.form);
		document.body.appendChild(this.form);

		this.text = this.form.elements[params.text.name];
		if (!this.text)
		{
			this.text = BX.create('INPUT', {props : {
				type : "hidden",
				name : params.text.name,
				value : ""
			}});
			this.form.appendChild(this.text);
		}
		this.block = BX.create("DIV", {className : "bx-additional-block-data"});
		this.form.appendChild(this.block);

		this.simpleForm = new simpleForm(this);
		this.extendedForm = new extendedForm(this, params);
		this.currentForm = null;

		repo[this.id] = this;

		this.initEvents();

		this.controllers = {};
		this.initControllers(params["CID"]);

		BX.onCustomEvent(window, "onMPFIsInitialized", [this]);

	};
	BX.MPF.prototype = {
		errors : {
			error00 : "BX.MPL: Mobile Application is obsolete.",
			error01 : "BX.MPL: form does not exist."
		},
		initEvents : function() {
			BX.addCustomEvent(this.simpleForm, 'onFormSubmitted', BX.delegate(this.submitPlain, this));
			BX.addCustomEvent(this.simpleForm, 'onFileSubmitted', BX.delegate(this.submitBase64, this));
			BX.addCustomEvent(this.simpleForm, 'onUserIsWriting', BX.delegate(this.writing, this));
			BX.addCustomEvent(this.extendedForm, 'onFormSubmitted', BX.delegate(this.submitExtended, this));
		},
		initControllers : function(controllers) {
			if (controllers || typeof controllers == "object")
			{
				var cid, bound = false;
				for (cid in controllers)
				{
					if (controllers.hasOwnProperty(cid))
					{
						if (controllers[cid]["USER_TYPE_ID"] == "disk_file")
						{
							this.controllers[cid] = new diskController(this, cid, controllers[cid]);

							if (!bound)
							{
								BX.addCustomEvent(this, 'onBase64Submitted', this.controllers[cid]["handleBase64"]);
								BX.addCustomEvent(this, 'onBase64Upload', this.controllers[cid]["uploadBase64"]);

								BX.addCustomEvent(this, 'onExtendedSubmitted', this.controllers[cid]["prepareToSaveUF"]);
								bound = true;
							}

							BX.addCustomEvent(this.extendedForm, 'onEditCommentUF', this.controllers[cid]["catchUF"]);
							BX.addCustomEvent(this.extendedForm, 'onApplyComment', this.controllers[cid]["parseUF"]);
						}
					}
				}
			}
		},
		destroy : function() {
			BX.remove(this.form);
			BX.onCustomEvent(this.handler, 'onMPFHasBeenDestroyed', [this.id, repo[this.id], this]);
			repo[this.id] = null;
		},
		writing : function() {
			BX.onCustomEvent(this, 'onMPFUserIsWriting', [this.comment]);
		},
		setForm : function(extended) {
			this.currentForm = (extended === true ? this.extendedForm : this.simpleForm);
		},
		init : function(comment) {

			this.comment = comment;
			this.setForm(false);
			this.simpleForm.init(comment.text);
		},
		show : function(comment, edit) {
			BX.onCustomEvent(this, "onShow", [this, comment]);
			this.comment = comment;
			this.setForm(edit);
			this.currentForm.show(comment.text, comment.attachments);
		},
		clear : function() {
			if (this.currentForm !== null)
			{
				this.currentForm.clear();
			}
		},
		submitBase64 : function(text, base64)
		{
			var result = {filesToPost : false};

			BX.onCustomEvent(this, 'onBase64Submitted', [base64, result]); // Let controllers to check and prepare arrays to upload

			if (result["filesToPost"] !== false)
			{
				BX.onCustomEvent(this.comment, "onStart", [this.comment, text, [base64]]);

				BX.addCustomEvent(base64, "onUploadOk", BX.proxy(function(txt, file) { this.submit((BX.type.isNotEmptyString(text) ? text : txt), [file]);}, this));
				BX.addCustomEvent(base64, "onUploadError", BX.proxy(this.error, this));

				BX.onCustomEvent(base64, "onUploadStart", [base64]); // Start uploading
			}
			else
			{
				this.cancel();
			}
		},
		submitPlain : function(text, attachments) {
			if (BX.type.isNotEmptyString(text))
			{
				BX.onCustomEvent(this.comment, "onStart", [this.comment, text, attachments]);
				this.submit(text, attachments);
			}
			else
			{
				this.cancel();
			}
		},
		submitExtended : function(text, attachments, extraData) {
			if (!BX.type.isNotEmptyString(text))
			{
				this.cancel();
				return;
			}
			if (typeof extraData != 'undefined' && typeof extraData["uf"] != 'undefined')
			{
				for (var ii = 0, id, jj; ii < attachments.length; ii++)
				{
					if (attachments[ii] && attachments[ii]["id"] && extraData["uf"][attachments[ii]["id"]])
					{
						for (jj in extraData["uf"][attachments[ii]["id"]])
						{
							if (extraData["uf"][attachments[ii]["id"]].hasOwnProperty(jj))
							{
								if (!attachments[ii][jj])
								{
									attachments[ii][jj] = extraData["uf"][attachments[ii]["id"]][jj];
								}
							}
						}
						attachments[ii]["id"] = extraData["uf"][attachments[ii]["id"]]["fieldValue"];
					}
				}
			}
			BX.onCustomEvent(this.comment, "onStart", [this.comment, text, attachments]);
			var queue = new uploadQueue();
			BX.onCustomEvent(this, 'onExtendedSubmitted', [attachments, queue]); // Let controllers to check and prepare arrays to upload

			if (queue.hasSmthToUpload())
			{
				BX.addCustomEvent(queue, "onUploadOk", BX.proxy(function() { this.submit(text, attachments); }, this));
				BX.addCustomEvent(queue, "onUploadError", BX.proxy(this.error, this));
				queue.start(); // Start uploading
			}
			else
			{
				this.submit(text, attachments);
			}
		},
		cancel : function() {
			this.setForm(false);
			this.clear();
			BX.onCustomEvent(this.comment, "onCancel", [this.comment]);
		},
		error : function(error) {
			this.setForm(false);
			this.clear();
			BX.onCustomEvent(this.comment, "onError", [this.comment, error]);
		},
		submit : function(text, attachments, extraData) {
			this.setForm(false);
			this.clear();
			this.comment.text = text;
			this.text.value = this.comment.getText();
			this.comment.attachments = attachments;
			this.comment.extraData = extraData;

			BX.onCustomEvent(this.comment, "onSubmit", [this.comment]);
		},

		getForm : function(data) {
			return BX.ajax.prepareForm(this.form, data).data;
		},
		showWait : function() {
			if (this.currentForm !== null)
				this.currentForm.showWait();
		},
		closeWait : function() {
			if (this.currentForm !== null)
				this.currentForm.closeWait();
		}
	};
	BX.MPF.createInstance = function(params)
	{
		if (!repo[params["ID"]])
			new BX.MPF(params);
		return repo[params["ID"]];
	};
	BX.MPF.getInstance = function(id)
	{
		return repo[id];
	};

	BX.onCustomEvent(window, "main.post.form/mobile", ["mobile"]);
})();