<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Block\Adminhtml\System\Config;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Connection\ConnectService;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Psr\Log\LoggerInterface;

/**
 * The connection panel of Stores > Configuration > Faccelerate: status, API base and the
 * Connect / Check status / Disconnect buttons (plain POST forms with a form key, no JavaScript).
 */
class Connection extends Field
{
    private const RETURN_PARAM = 'faccelerate';
    private const RETURN_VALUE = 'connected';

    protected $_template = 'Faccelerate_Connect::system/config/connection.phtml';

    private bool $returnHandled = false;

    /** @var array{status: string, workspace_name: ?string}|null State from the automatic refresh */
    private ?array $state = null;

    private ?string $error = null;

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ConnectService $connectService,
        private readonly LoggerInterface $logger,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * The panel spans the whole row: no label, no "Use system value" checkbox.
     */
    public function render(AbstractElement $element): string
    {
        $this->setData('element', $element);

        return $this->_decorateRowHtml(
            $element,
            '<td colspan="4" class="faccelerate-connection">' . $this->_toHtml() . '</td>'
        );
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * When the Faccelerate app sends the admin user back (?faccelerate=connected), ask it where the connection
     * stands. Idempotent per request; a failure is shown in the panel instead of breaking the config page.
     */
    public function handleReturn(): void
    {
        if ($this->returnHandled) {
            return;
        }
        $this->returnHandled = true;

        if ($this->getRequest()->getParam(self::RETURN_PARAM) !== self::RETURN_VALUE) {
            return;
        }
        try {
            $this->state = $this->connectService->refresh();
        } catch (LocalizedException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Faccelerate: status check failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->error = (string)__('Checking the Faccelerate status failed. See var/log/system.log for details.');
        }
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * One of the Config::STATUS_* constants.
     */
    public function getStatus(): string
    {
        return $this->state['status'] ?? $this->config->getStatus();
    }

    public function getStatusLabel(): Phrase
    {
        return match ($this->getStatus()) {
            Config::STATUS_CONNECTED => __('Connected to "%1"', $this->getWorkspaceName() ?: (string)__('your workspace')),
            Config::STATUS_PENDING => __('Waiting for you to pick a workspace'),
            default => __('Not connected'),
        };
    }

    public function getWorkspaceName(): ?string
    {
        return $this->state !== null ? $this->state['workspace_name'] : $this->config->getWorkspaceName();
    }

    public function getApiBaseUrl(): string
    {
        return $this->config->getApiBaseUrl();
    }

    /**
     * Where the admin user can continue picking a workspace while the status is pending.
     */
    public function getConnectUrl(): ?string
    {
        return $this->getStatus() === Config::STATUS_PENDING ? $this->config->getConnectUrl() : null;
    }

    public function getActionUrl(string $action): string
    {
        return $this->getUrl('faccelerate/connection/' . $action);
    }

    /**
     * The panel sits inside the configuration page's form, where a nested form would be dropped, so its buttons
     * link to a confirm page that has its own form.
     */
    public function getConfirmUrl(string $action): string
    {
        return $this->getUrl('faccelerate/connection/confirm', ['do' => $action]);
    }
}
