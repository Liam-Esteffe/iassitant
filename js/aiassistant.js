/* Copyright (C) 2026 SuperAdmin */

(function () {
	"use strict";

	function escapeHtml(value) {
		return String(value == null ? "" : value)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
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

	function renderActions(widget, container, actions) {
		if (!actions || !actions.length) {
			return;
		}
		var wrap = document.createElement("div");
		wrap.className = "aiassistant-actions";
		actions.forEach(function (action) {
			var row = document.createElement("div");
			row.className = "aiassistant-action";
			row.innerHTML =
				'<span class="aiassistant-action-label">' + escapeHtml(action.label || action.type) + "</span>" +
				'<button type="button" class="button button-save aiassistant-confirm">' + escapeHtml(widget.getAttribute("data-lang-confirm")) + "</button>";
			row.querySelector(".aiassistant-confirm").addEventListener("click", function () {
				executeAction(widget, action.id, row);
			});
			wrap.appendChild(row);
		});
		container.appendChild(wrap);
		container.scrollTop = container.scrollHeight;
	}

	function setBusy(widget, busy) {
		var input = widget.querySelector(".aiassistant-input");
		var button = widget.querySelector(".aiassistant-send");
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
					return;
				}
				var bot = appendMessage(messages, data.analysis || "", "aiassistant-msg-bot");
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
				row.parentNode.removeChild(row);
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

	function init(widget) {
		if (!widget || widget.getAttribute("data-aiassistant-ready") === "1") {
			return;
		}
		widget.setAttribute("data-aiassistant-ready", "1");
		var form = widget.querySelector(".aiassistant-form");
		var input = widget.querySelector(".aiassistant-input");
		if (!form || !input) {
			return;
		}
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

	function boot() {
		document.querySelectorAll(".aiassistant-widget").forEach(init);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();
