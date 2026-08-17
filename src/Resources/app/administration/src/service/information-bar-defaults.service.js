const ApiService = Shopware.Classes.ApiService;

class InformationBarDefaultsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/act-information-bar/defaults') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'informationBarDefaultsService';
    }

    load(salesChannelId = null) {
        return this.httpClient
            .get(this.getApiBasePath(), {
                headers: this.getBasicHeaders(),
                params: salesChannelId ? { salesChannelId } : {},
            })
            .then((response) => ApiService.handleResponse(response));
    }

    getDefaults(salesChannelId = null) {
        return this.load(salesChannelId).then((data) => data.defaults ?? {});
    }

    getTimezone() {
        return this.load().then((data) => data.timezone ?? 'UTC');
    }

    save(defaults, timezone, salesChannelId = null) {
        return this.httpClient
            .post(this.getApiBasePath(), { defaults, timezone, salesChannelId }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

const { Application } = Shopware;

Application.addServiceProvider('informationBarDefaultsService', (container) =>
    new InformationBarDefaultsService(Application.getContainer('init').httpClient, container.loginService));
