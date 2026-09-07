/* Copyright (C) 2026 Liam Esteffe */

(function () {
	"use strict";

	var HISTORY_KEY = "aiassistant-history";
	var HISTORY_MAX = 30;

	function escapeHtml(value) {
		return String(value == null ? "" : value)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function loadHistory() {
		try {
			var raw = window.sessionStorage.getItem(HISTORY_KEY);
			return raw ? JSON.parse(raw) : [];
		} catch (err) {
			return [];
		}
	}

	function saveHistoryEntry(entry) {
		var history = loadHistory();
		history.push(entry);
		if (history.length > HISTORY_MAX) {
			history = history.slice(history.length - HISTORY_MAX);
		}
		try {
			window.sessionStorage.setItem(HISTORY_KEY, JSON.stringify(history));
		} catch (err) {
			/* ignore quota */
		}
	}

	function appendMessage(container, text, cssClass, html) {
		var node = document.createElement("div");
		node.className = "aiassistant-msg " + cssClass;
		if (html) {
			node.innerHTML = html;
		} else {
			node.textContent = text;
		}
		container.appendChild(node);
		container.scrollTop = container.scrollHeight;
		return node;
	}

	function renderPreview(preview) {
		if (!preview || !preview.length) {
			return "";
		}
		var html = '<dl class="aiassistant-preview">';
		preview.forEach(function (row) {
			html += "<dt>" + escapeHtml(row.label) + "</dt><dd>" + escapeHtml(row.value) + "</dd>";
		});
		html += "</dl>";
		return html;
	}

	function renderActions(widget, container, actions) {
		if (!actions || !actions.length) {
			return;
		}
		var canWrite = widget.getAttribute("data-can-write") === "1";
		var wrap = document.createElement("div");
		wrap.className = "aiassistant-actions";
		actions.forEach(function (action) {
			var row = document.createElement("div");
			row.className = "aiassistant-action";
			var html = '<div class="aiassistant-action-label">' + escapeHtml(action.label || action.type) + "</div>";
			html += renderPreview(action.preview || []);
			if (canWrite && action.can_write !== 0) {
				html += '<div class="aiassistant-action-buttons">';
				html += '<button type="button" class="button aiassistant-confirm">' + escapeHtml(widget.getAttribute("data-lang-confirm")) + "</button>";
				html += "</div>";
				html += '<div class="aiassistant-action-execute hidden">';
				html += '<div class="opacitymedium small">' + escapeHtml(widget.getAttribute("data-lang-preview")) + "</div>";
				html += '<button type="button" class="button button-save aiassistant-run">' + escapeHtml(widget.getAttribute("data-lang-execute")) + "</button>";
				html += '<button type="button" class="button aiassistant-cancel">' + escapeHtml(widget.getAttribute("data-lang-cancel")) + "</button>";
				html += "</div>";
			}
			row.innerHTML = html;
			var confirmBtn = row.querySelector(".aiassistant-confirm");
			var runBtn = row.querySelector(".aiassistant-run");
			var cancelBtn = row.querySelector(".aiassistant-cancel");
			var executeBox = row.querySelector(".aiassistant-action-execute");
			if (confirmBtn && executeBox) {
				confirmBtn.addEventListener("click", function () {
					executeBox.classList.remove("hidden");
					confirmBtn.classList.add("hidden");
				});
			}
			if (cancelBtn && executeBox && confirmBtn) {
				cancelBtn.addEventListener("click", function () {
					executeBox.classList.add("hidden");
					confirmBtn.classList.remove("hidden");
				});
			}
			if (runBtn) {
				runBtn.addEventListener("click", function () {
					executeAction(widget, action.id, row);
				});
			}
			wrap.appendChild(row);
		});
		container.appendChild(wrap);
		container.scrollTop = container.scrollHeight;
	}

	function setBusy(widget, busy) {
		var input = widget.querySelector(".aiassistant-input");
		var button = widget.querySelector(".aiassistant-send");
		widget.querySelectorAll(".aiassistant-quick").forEach(function (item) {
			item.disabled = busy;
		});
		if (input) {
			input.disabled = busy;
		}
		if (button) {
			button.disabled = busy;
		}
	}

	function postJson(url, token, payload) {
		return fetch(url + (url.indexOf("?") >= 0 ? "&" : "?") + "token=" + encodeURIComponent(token), {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json",
				"X-Requested-With": "XMLHttpRequest"
			},
			body: JSON.stringify(payload)
		}).then(function (response) {
			return response.text().then(function (text) {
				var data = {};
				try {
					data = text ? JSON.parse(text) : {};
				} catch (err) {
					data = { success: false, error: text || "Invalid JSON" };
				}
				data._http = response.status;
				return data;
			});
		});
	}

	function ask(widget, question) {
		var messages = widget.querySelector(".aiassistant-messages");
		var token = widget.getAttribute("data-token") || "";
		appendMessage(messages, question, "aiassistant-msg-user");
		saveHistoryEntry({ role: "user", text: question });
		var loading = appendMessage(messages, widget.getAttribute("data-lang-loading"), "aiassistant-msg-bot aiassistant-loading");
		setBusy(widget, true);

		postJson(widget.getAttribute("data-chat-url"), token, { question: question })
			.then(function (data) {
				loading.parentNode.removeChild(loading);
				if (data.token) {
					widget.setAttribute("data-token", data.token);
				}
				if (!data.success) {
					appendMessage(messages, data.error || widget.getAttribute("data-lang-error"), "aiassistant-msg-error");
					saveHistoryEntry({ role: "error", text: data.error || widget.getAttribute("data-lang-error") });
					return;
				}
				var bot = appendMessage(messages, data.analysis || "", "aiassistant-msg-bot");
				saveHistoryEntry({ role: "bot", text: data.analysis || "" });
				renderActions(widget, bot, data.actions || []);
			})
			.catch(function () {
				if (loading.parentNode) {
					loading.parentNode.removeChild(loading);
				}
				appendMessage(messages, widget.getAttribute("data-lang-error"), "aiassistant-msg-error");
			})
			.then(function () {
				setBusy(widget, false);
			});
	}

	function executeAction(widget, actionId, row) {
		var messages = widget.querySelector(".aiassistant-messages");
		var token = widget.getAttribute("data-token") || "";
		var buttons = row.querySelectorAll("button");
		buttons.forEach(function (button) {
			button.disabled = true;
		});
		var loading = appendMessage(messages, widget.getAttribute("data-lang-executing"), "aiassistant-msg-bot aiassistant-loading");

		postJson(widget.getAttribute("data-execute-url"), token, { action_id: actionId })
			.then(function (data) {
				loading.parentNode.removeChild(loading);
				if (data.token) {
					widget.setAttribute("data-token", data.token);
				}
				if (!data.success) {
					appendMessage(messages, data.error || widget.getAttribute("data-lang-error"), "aiassistant-msg-error");
					saveHistoryEntry({ role: "error", text: data.error || widget.getAttribute("data-lang-error") });
					buttons.forEach(function (button) {
						button.disabled = false;
					});
					return;
				}
				var html = escapeHtml(data.message || "");
				if (data.url) {
					html += (html ? "<br>" : "") + data.url;
				}
				appendMessage(messages, "", "aiassistant-msg-success", html);
				saveHistoryEntry({ role: "success", text: data.message || "" });
				if (row.parentNode) {
					row.parentNode.removeChild(row);
				}
			})
			.catch(function () {
				if (loading.parentNode) {
					loading.parentNode.removeChild(loading);
				}
				appendMessage(messages, widget.getAttribute("data-lang-error"), "aiassistant-msg-error");
				buttons.forEach(function (button) {
					button.disabled = false;
				});
			});
	}

	function restoreHistory(widget) {
		var messages = widget.querySelector(".aiassistant-messages");
		var history = loadHistory();
		if (!history.length) {
			return;
		}
		history.forEach(function (entry) {
			if (entry.role === "user") {
				appendMessage(messages, entry.text, "aiassistant-msg-user");
			} else if (entry.role === "error") {
				appendMessage(messages, entry.text, "aiassistant-msg-error");
			} else if (entry.role === "success") {
				appendMessage(messages, entry.text, "aiassistant-msg-success");
			} else {
				appendMessage(messages, entry.text, "aiassistant-msg-bot");
			}
		});
	}

	function init(widget) {
		if (!widget || widget.getAttribute("data-aiassistant-ready") === "1") {
			return;
		}
		widget.setAttribute("data-aiassistant-ready", "1");
		restoreHistory(widget);
		var form = widget.querySelector(".aiassistant-form");
		var input = widget.querySelector(".aiassistant-input");
		if (form && input) {
			form.addEventListener("submit", function (event) {
				event.preventDefault();
				var question = (input.value || "").trim();
				if (!question) {
					return;
				}
				input.value = "";
				ask(widget, question);
			});
		}
		widget.querySelectorAll(".aiassistant-quick").forEach(function (button) {
			button.addEventListener("click", function () {
				var prompt = button.getAttribute("data-prompt") || "";
				if (prompt) {
					ask(widget, prompt);
				}
			});
		});
	}

	function boot() {
		document.querySelectorAll(".aiassistant-widget").forEach(init);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();
