(function (global) {
  "use strict";

  function resolveApiBase() {
    var path = global.location.pathname.replace(/\\/g, "/");

    if (
      /\/login(\/|$)/.test(path) ||
      /\/chamados(\/|$)/.test(path) ||
      /\/views(\/|$)/.test(path)
    ) {
      return "../api/";
    }

    return "/api/";
  }

  var API_BASE = resolveApiBase();

  function parseJsonResponse(response) {
    return response.text().then(function (bodyText) {
      try {
        return JSON.parse(bodyText);
      } catch (error) {
        return {
          status: false,
          message:
            "Resposta inválida do servidor (" +
            response.status +
            "). Verifique IIS/PHP. Trecho: " +
            (bodyText || "").slice(0, 140),
        };
      }
    });
  }

  function apiRequest(endpoint, options) {
    var config = options || {};
    var url = API_BASE + endpoint.replace(/^\//, "");
    var headers = config.headers || {};

    if (config.body && !(config.body instanceof FormData) && !headers["Content-Type"]) {
      headers["Content-Type"] = "application/json";
    }

    return fetch(url, {
      method: config.method || "GET",
      headers: headers,
      body: config.body,
      credentials: "same-origin",
    }).then(function (response) {
      return parseJsonResponse(response).then(function (payload) {
        return {
          ok: response.ok,
          status: response.status,
          payload: payload,
        };
      });
    });
  }

  function apiPostJson(endpoint, data) {
    return apiRequest(endpoint, {
      method: "POST",
      body: JSON.stringify(data || {}),
    });
  }

  function apiPostForm(endpoint, formData) {
    return apiRequest(endpoint, {
      method: "POST",
      body: formData,
    });
  }

  function apiGet(endpoint) {
    return apiRequest(endpoint, { method: "GET" });
  }

  global.PortalApi = {
    base: API_BASE,
    request: apiRequest,
    postJson: apiPostJson,
    postForm: apiPostForm,
    get: apiGet,
  };
})(window);
