const ApiService = Shopware.Classes.ApiService;

class InformationBarPreviewService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/act-information-bar/preview-text') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'informationBarPreviewService';
    }

    previewText(message, expiredText = null) {
        return this.httpClient
            .post(this.getApiBasePath(), { message, expiredText }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

const { Application } = Shopware;

Application.addServiceProvider('informationBarPreviewService', (container) =>
    new InformationBarPreviewService(Application.getContainer('init').httpClient, container.loginService));
