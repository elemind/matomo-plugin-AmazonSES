<!--
  AmazonSES plugin for Matomo

  @link    https://github.com/elemind/matomo-plugin-AmazonSES
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="amazonSesAdmin">
    <ContentBlock :content-title="translate('AmazonSES_StatusTitle')">
      <ActivityIndicator :loading="isLoadingStatus" />

      <div v-if="status">
        <div class="alert alert-danger" v-if="status.error">{{ status.error }}</div>
        <div class="alert alert-warning" v-if="!status.emailsEnabled">
          {{ translate('AmazonSES_EmailsDisabled') }}
        </div>
        <div
          class="alert alert-warning"
          v-if="status.account && !status.account.productionAccessEnabled"
        >
          {{ translate('AmazonSES_SandboxHelp') }}
        </div>

        <table class="entityTable amazonSesStatus">
          <tbody>
            <tr>
              <td>{{ translate('AmazonSES_Region') }}</td>
              <td>{{ status.region }}</td>
            </tr>
            <tr v-if="status.endpoint">
              <td>{{ translate('AmazonSES_Endpoint') }}</td>
              <td><code>{{ status.endpoint }}</code></td>
            </tr>
            <tr>
              <td>{{ translate('AmazonSES_CredentialsSource') }}</td>
              <td>{{ status.credentialsSource || translate('AmazonSES_None') }}</td>
            </tr>
            <tr>
              <td>{{ translate('AmazonSES_Sender') }}</td>
              <td>{{ status.senderEmail }}</td>
            </tr>
            <tr>
              <td>{{ translate('AmazonSES_ConfigurationSet') }}</td>
              <td>{{ status.configurationSet || translate('AmazonSES_None') }}</td>
            </tr>
            <template v-if="status.account">
              <tr>
                <td>{{ translate('AmazonSES_Sandbox') }}</td>
                <td>
                  {{ status.account.productionAccessEnabled
                    ? translate('AmazonSES_SandboxNo')
                    : translate('AmazonSES_SandboxYes') }}
                </td>
              </tr>
              <tr>
                <td>{{ translate('AmazonSES_SendingEnabled') }}</td>
                <td>
                  {{ status.account.sendingEnabled ? translate('General_Yes') : translate('General_No') }}
                </td>
              </tr>
              <tr>
                <td>{{ translate('AmazonSES_Quota24h') }}</td>
                <td>{{ status.account.sentLast24Hours }} / {{ status.account.max24HourSend }}</td>
              </tr>
              <tr>
                <td>{{ translate('AmazonSES_MaxSendRate') }}</td>
                <td>{{ status.account.maxSendRate }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <p>
        <button class="btn btn-flat" @click="loadStatus()" :disabled="isLoadingStatus">
          {{ translate('AmazonSES_Refresh') }}
        </button>
        <a :href="settingsUrl" class="btn btn-flat">{{ translate('AmazonSES_SettingsLink') }}</a>
      </p>
    </ContentBlock>

    <ContentBlock :content-title="translate('AmazonSES_TestTitle')">
      <div class="alert alert-success" v-if="testResult">
        {{ translate('AmazonSES_TestSent', testResult.recipient, testResult.messageId) }}
      </div>
      <div class="alert alert-danger" v-if="testError">{{ testError }}</div>

      <Field
        uicontrol="text"
        name="amazonSesTestRecipient"
        v-model="recipient"
        :title="translate('AmazonSES_TestRecipient')"
        :inline-help="translate('AmazonSES_TestRecipientHelp')"
      />

      <button class="btn" @click="sendTest()" :disabled="isSending">
        {{ translate('AmazonSES_SendTest') }}
      </button>
      <ActivityIndicator :loading="isSending" />
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import {
  ActivityIndicator,
  AjaxHelper,
  ContentBlock,
  MatomoUrl,
} from 'CoreHome';
import { Field } from 'CorePluginsAdmin';

interface AccountStatus {
  productionAccessEnabled: boolean;
  sendingEnabled: boolean;
  enforcementStatus: string;
  max24HourSend: number | null;
  maxSendRate: number | null;
  sentLast24Hours: number | null;
}

interface Status {
  region: string;
  endpoint: string | null;
  configurationSet: string;
  senderEmail: string;
  emailsEnabled: boolean;
  credentialsSource: string | null;
  account: AccountStatus | null;
  error: string | null;
}

interface TestResult {
  recipient: string;
  messageId: string;
}

interface SesAdminState {
  status: Status | null;
  isLoadingStatus: boolean;
  recipient: string;
  isSending: boolean;
  testResult: TestResult | null;
  testError: string;
}

export default defineComponent({
  props: {
    defaultRecipient: {
      type: String,
      default: '',
    },
  },
  components: {
    ActivityIndicator,
    ContentBlock,
    Field,
  },
  data(): SesAdminState {
    return {
      status: null,
      isLoadingStatus: false,
      recipient: this.defaultRecipient,
      isSending: false,
      testResult: null,
      testError: '',
    };
  },
  created() {
    this.loadStatus();
  },
  computed: {
    settingsUrl(): string {
      return `?${MatomoUrl.stringify({
        ...MatomoUrl.urlParsed.value,
        module: 'CoreAdminHome',
        action: 'generalSettings',
      })}#AmazonSES`;
    },
  },
  methods: {
    loadStatus() {
      this.isLoadingStatus = true;
      AjaxHelper.fetch<Status>({
        method: 'AmazonSES.getStatus',
      }).then((status) => {
        this.status = status;
      }).finally(() => {
        this.isLoadingStatus = false;
      });
    },
    sendTest() {
      this.isSending = true;
      this.testResult = null;
      this.testError = '';
      AjaxHelper.post<TestResult>(
        { method: 'AmazonSES.sendTestEmail' },
        { email: this.recipient },
        { createErrorNotification: false },
      ).then((result) => {
        this.testResult = result;
        this.loadStatus();
      }).catch((e: Error) => {
        this.testError = e.message;
      }).finally(() => {
        this.isSending = false;
      });
    },
  },
});
</script>

<style lang="less" scoped>
.amazonSesStatus {
  margin-bottom: 16px;

  td:first-child {
    width: 280px;
    font-weight: bold;
  }
}
</style>
