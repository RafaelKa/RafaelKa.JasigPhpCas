<?php
namespace RafaelKa\JasigPhpCas\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Arrays as ArraysUtility;

/**
 * @Flow\Scope("singleton")
 */
class ValidateCommandController extends \TYPO3\Flow\Cli\CommandController
{

    /**
     * @Flow\Inject
     * @var \RafaelKa\JasigPhpCas\Service\Mapping\Validator\DefaultMapperValidator
     */
     protected $defaultMapperValidator;

     /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Configuration\ConfigurationManager
     */
    protected $configurationManager;

    /**
     * An example command
     *
     * This command validates given provider and gives feedback for invalid settings.
     *
     * @param string $providerName Provider name to validate.
     * @return void
     */
    public function providerCommand($providerName)
    {
        $this->renderPrettyYaml($providerName);
        $this->renderErrors($providerName);
        //$this->pr
    }

    /**
     *
     * @param type $providerName
     * @return void
     */
    private function renderErrors($providerName)
    {
        $errors = $this->defaultMapperValidator->getValidationErrors($providerName);
        $settings = array();
        if (!empty($errors)) {
            $this->outputLine('<b>Following settings are wrong or empty: </b>' . PHP_EOL);
            /* @var $error \TYPO3\Flow\Error\Error */
            foreach ($errors as $error) {
                $this->outputLine(str_repeat('-', self::MAXIMUM_LINE_LENGTH));
                $this->outputLine('<b># %s</b> on ', array($error->getCode()));
                $this->outputLine('<b>%s:</b>', array($error->getTitle()));
                $this->outputLine('%s', array($error->render()));
            }
            $this->outputLine();
        }
    }

    /**
     *
     *
     * @param string $errors
     * @return void
     */
    private function renderPrettyYaml($providerName)
    {
        $errors = $this->defaultMapperValidator->getValidationErrors($providerName);
        if (empty($errors)) {
            return;
        }
        $settings = array();
        $this->outputLine(PHP_EOL . 'Settings for <b>"%s"</b> are not valid!' . PHP_EOL, array($providerName));
        /* @var $error \TYPO3\Flow\Error\Error */
        foreach ($errors as $error) {
            $parentPath = implode('.', explode('.', $error->getTitle(), -1));
            $parentNode = ArraysUtility::getValueByPath($settings, $parentPath);
            if (is_string($parentNode) && preg_match('/^<b># /', $parentNode)) {
                continue;
            }
            $settings = ArraysUtility::setValueByPath($settings, $error->getTitle(), '<b># ' . $error->getCode() . '</b>');
        }

        // fix representation of roles in yaml
        $roleErrors = ArraysUtility::getValueByPath($settings, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.Roles');
        $maxNumber = max(array_keys($roleErrors));
        for ($index = 0; $index <= $maxNumber; $index++) {
            if (!empty($roleErrors[$index])) {
                continue;
            }
            $path = 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.Roles.' . $index;
            $realSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $path);
            $settings = ArraysUtility::setValueByPath($settings, $path, $realSettings);
        }
        $roleErrors = ArraysUtility::getValueByPath($settings, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.Roles');
        ksort($roleErrors);
        $settings = ArraysUtility::setValueByPath($settings, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.Roles', $roleErrors);


        $this->outputLine('<b>See this on YAML:</b> errors are bold and with # marked.');
        $this->outputLine('<b>' . str_repeat('-', self::MAXIMUM_LINE_LENGTH) . '</b>');

        $yaml = \Symfony\Component\Yaml\Yaml::dump($settings, 99, 2);
        $this->output($yaml);
        $this->outputLine('<b>' . str_repeat('-', self::MAXIMUM_LINE_LENGTH) . '</b>');
    }

    /**
     * An example command
     *
     * The comment of this command method is also used for TYPO3 Flow's help screens. The first line should give a very short
     * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
     * does. You might also give some usage example.
     *
     * It is important to document the parameters with param tags, because that information will also appear in the help
     * screen.
     *
     * @return void
     */
    public function allCommand()
    {
        $errors = $this->defaultMapperValidator->getValidationErrors();
        if (empty($errors)) {
            $this->outputLine('Settings by all providers are valid');
            $this->quit();
        }
        foreach ($errors as $providerName => $providerErrors) {
            $this->outputLine('<b>' . str_repeat('#', self::MAXIMUM_LINE_LENGTH) . '</b>');
            $this->renderPrettyYaml($providerName);
            $this->renderErrors($providerName);
        }
        $this->outputLine('<b>' . str_repeat('#', self::MAXIMUM_LINE_LENGTH) . '</b>');
    }
}
