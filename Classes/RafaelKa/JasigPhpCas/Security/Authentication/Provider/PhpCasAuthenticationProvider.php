<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\Provider;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Security\Authentication\AuthenticationProviderInterface;

/**
 * An authentication provider that authenticates
 * RafaelKa\JasigPhpCas\Security\Authentication\Token\CASToken tokens.
 */
class PhpCasAuthenticationProvider implements AuthenticationProviderInterface
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $options;

    /**
     * @Flow\Inject
     * @var \RafaelKa\JasigPhpCas\Service\CasManager
     */
    protected $casManager;

    /**
     * Constructor
     *
     * @param string $name The name of this authentication provider
     * @param array $options Additional configuration options
     */
    public function __construct($name, array $options = array())
    {
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Checks the given token for validity and sets the token authentication status
     * accordingly (success, if __casAuthenticationProviderName == $this->name).
     *
     * @param \TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken The token to be authenticated
     * @return void
     * @throws \TYPO3\Flow\Security\Exception\UnsupportedAuthenticationTokenException
     */
    public function authenticate(\TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken)
    {
        if ($authenticationToken->getAuthenticationProviderName() === $this->name) {
            $casAttributes = $this->casManager->authenticate($this->name);
            if (!empty($casAttributes) && is_array($casAttributes)) {
                $account = $this->casManager->getAccount($this->name, $casAttributes);
                $authenticationToken->setAccount($account);
                $authenticationToken->setAuthenticationStatus(\TYPO3\Flow\Security\Authentication\TokenInterface::AUTHENTICATION_SUCCESSFUL);
            }
        }
    }

    /**
     * Returns TRUE if the given token can be authenticated by this provider
     *
     * @param \TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken The token that should be authenticated
     * @return boolean TRUE if the given token class can be authenticated by this provider
     */
    public function canAuthenticate(\TYPO3\Flow\Security\Authentication\TokenInterface $authenticationToken)
    {
        if ($authenticationToken->getAuthenticationProviderName() === $this->name) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the class names of the tokens this provider can authenticate.
     *
     * @return array
     */
    public function getTokenClassNames()
    {
        return $this->casManager->getTokenClassNamesByProviderName($this->name);
    }
}
