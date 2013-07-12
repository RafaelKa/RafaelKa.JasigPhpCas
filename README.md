#RafaelKa.JasigPhpCas

A package for TYPO3 Flow to authenticate by CAS-Server.
A CAS("Central Authentication Service" by Jasig) Client. 

## Installation
composer install rafaelka-jasig-php-cas
here is a sample configuration file.
```yaml
TYPO3:
  Flow:
    security:
      authentication:
        providers:
          AuthenticationProviderByTHM:
            provider: RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider
            providerOptions:
              # A CAS client settings for this provider.
              casClient:
                server_version: '2.0'
                server_hostname: cas.thm.de
                server_port: 443
                server_uri: /cas
                casServerCACertificatePath: 'resource://RafaelKa.JasigPhpCas/Certificates/CAS-Server/thmCaCert.pem'
                # Optional: Default is FALSE
                noCasServerValidation: FALSE
                # Optional: Default is TRUE
                changeSessionID: TRUE
                # Optional: Default is 'resource://RafaelKa.JasigPhpCas/PHP/Debug/'
                debugPath: 'resource://RafaelKa.JasigPhpCas/PHP/Debug/'
              Mapping:
                # If defined, then all mapping settings will be ignored and not validated
                # Optional: Default is RafaelKa\JasigPhpCas\Service\DefaultMapper 
                # MapperClass: RafaelKa\JasigPhpCas\Service\DefaultMapper
                # Optional: Default is FALSE and the same as ....Mapping.Account.persistAccounts. If one of both is TRUE then account will be persisted.
                persistAccounts: FALSE
                # Optional: Default is FALSE
                # This option is ignored by: persistAccounts: FALSE
                persistParties: FALSE
                ################################################################
                # Optional: Default is "No Redirect" 
                # Is usefull if you want users "accepting privacy policy" by new users.
                # This option is ignored by: "persistAccounts: FALSE" but 
                # if defined then you must persist acoount and party by yourself.
                redirectByNewUser:
                  Controller: 
                  Action:
                ################################################################
                Account:
                  # Optional: Default is FALSE but if is set to TRUE, then accountidentifier can be omitted.
                  doNotMapAccount: FALSE
                  # Optional: Default is TRUE and the same as ....Mapping.persistAccounts. If one of both is TRUE then account will be persisted.
                  persistAccounts: TRUE
                  # path to value in casAttributes array
                  accountidentifier: mail
                  # Optional: Default is NULL but if is set expirationDate = periodOfValidity+creationDate
                  periodOfValidity: 183
                  # Optional: Default is NULL.
                  authenticationProviderName: DefaultProvider
                  forceProviderName: FALSE
                  # Optional: Default is NULL. If is set, then checks CasManager the existency of provider name.
                  # This option is ignored by: "persistAccounts: FALSE"
                  useProviderNameByPersistingAccount: DefaultProvider
                  # Optional: Default is NULL. If is set, then checking the existency of provider name is skipped.
                  # This option overrides "useProviderNameByPersistingAccount" but is ignored to by: "persistAccounts: FALSE"
                  forceUseProviderNameByPersistingAccount: DefaultProvider
                Roles:
                  ##############################################################
                  # Description:
                  #   requered:
                  #     "identifier:"       TYPE expected: string
                  #                         aim            path to value in casAttributes array.
                  #                         example:       office.department for $casAttributes['office']['department']
                  #
                  #     "staticIdentifier:" TYPE expected: string
                  #                         overrides:     "identifier" 
                  #                         aim:           DeafaultMapper will not look in CAS-attributes 
                  #                                        and simply assign difined role to account.
                  #                                        Uses role identifier from Policy.yaml for role.
                  #
                  #     "packageKey"        TYPE expected: string
                  #                         aim:           Uses package key where roles are defined (see Policy.yaml).
                  #
                  #   optional:
                  #     "rewriteRoles"      TYPE expected: array
                  #                         depends on:    "identifier:"
                  #                         ignored by:    "staticIdentifier:"
                  #                         aim:           if roles by CAS-Attributes are not human readable 
                  #                                        then can you rewrite them.
                  ##
                  # Example: (Note: "-" are requered)
                  #-
                  #  identifier: office.department
                  #  packageKey: RafaelKa.JasigPhpCas.Demo
                  #  rewriteRoles: 
                  #    S: Student
                  #    P: Professor
                  #    D: Director
                  #    R: Rector
                  #-
                  #  staticIdentifier: casUser
                  #  packageKey: RafaelKa.JasigPhpCas.Demo
                  #-
                  #  identifier: userClass
                  #  packageKey: RafaelKa.JasigPhpCas.Demo
                  ## 
                  # WARNING: leastways one role must be defined.
                  #
                  ##############################################################
                  - 
                    ############################################################
                    # One of both options must be defined.
                    identifier: userClass
                    # "staticIdentifier" overrides "identifier" and uses role identifier from Policy.yaml
                    # mapper will not look in CAS-attributes and simply assign difined role to account.
                    staticIdentifier: casUser
                    # 
                    ############################################################
                    # requered
                    packageKey: RafaelKa.JasigPhpCas.Demo
                  -
                    identifier: userClass
                    packageKey: RafaelKa.JasigPhpCas.Demo
                    # Optional: Default is NULL. This option is ignored if "staticIdentifier" is set.
                    rewriteRoles:
                      S: Student
                      M: Mitarbeiter
                      L: Lehrbeauftragter
                      P: Professor
                  -
                    identifier: department.0
                    packageKey: RafaelKa.JasigPhpCas.Demo
                  -
                    identifier: department.1
                    packageKey: RafaelKa.JasigPhpCas.Demo

                Party:
                  # Optional: Default is FALSE. If set to TRUE, then settings for person can be omitted.
                  doNotMapParty: FALSE
                  # Optional: Default is FALSE and the same as ....Mapping.persistParty. If one of both is TRUE then party will be persisted. Ignored if "persistAccounts" is FALSE.
                  persistParty: TRUE
                  Person:
                    name:
                      alias:
                      title:
                      firstName: firstnames
                      middleName:
                      lastName: surnames
                      otherName:
                    # Optional: Default is NULL
                    primaryElectronicAddress: 
                      identifier: mail
                      type: Email
                      usage: Work
                      # Static method has priority
                      staticType: Email
                      staticUsage: Work
                      # Default is TRUE
                      #approved: TRUE #use Flows way
          # Use default provider.
          DefaultProvider:
             provider: PersistedUsernamePasswordProvider

#RafaelKa:
#  JasigPhpCas:
#    Authentication:
#      Token:
#        allowedArguments: 
#          - casAuthenticationProviderName
#          - mest 
#          - best 
#          - fest
#        allowedInternalArguments:
```
