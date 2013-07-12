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
          DefaultProvider:
            provider: PersistedUsernamePasswordProvider
          AuthenticationProviderByTHM:
            # Requered: works only with this provider.
            provider: RafaelKa\JasigPhpCas\Security\Authentication\Provider\PhpCasAuthenticationProvider
            providerOptions:
            # The CAS client settings for this provider.
              casClient:
              ##################################################################
              # Requred: Hostname from CAS-Server
                server_hostname: cas.thm.de
              # Requered: Path to Root-Certifiacete(X.509) from CAS-Server
                casServerCACertificatePath: 'resource://RafaelKa.JasigPhpCas.Demo/Private/Certificates/CAS-Server/thmCaCert.pem'
              # Optional: Default is '/cas' URI from CAS-Service
                server_uri: '/cas'
              # Optional: Default is 443
                server_port: 443
              # Optional: Default is '2.0'
                server_version: '2.0'
              # Optional: Default is FALSE but if defined then casServerCACertificatePath: is ignored.
                noCasServerValidation: FALSE
              # Optional: Default is 'resource://RafaelKa.JasigPhpCas/PHP/Debug/'
                debugPath: 'resource://RafaelKa.JasigPhpCas/PHP/Debug/'
              ## Following option have no functionaty @todo:
              # Optional: Default is TRUE
                changeSessionID: TRUE
              # Optioanal: Default is NULL
                serverProxyValidateURL: ''
              # Optioanal: Default is NULL
                serverLoginURL: ''
              # Optioanal: Default is NULL
                serverLogoutURL:
              # Optioanal: Default is NULL
                serverProxyValidateURL:
              # Optioanal: Default is NULL
                serverSamlValidateURL:
              # Optioanal: Default is NULL
                serverServiceValidateURL:
              # Optioanal: Default is NULL :: since CAS 4.0 no functionality
                singleSignoutCallback:
              #
              ##################################################################
              Mapping:
              ##################################################################
              # Optional: Default is RafaelKa\JasigPhpCas\Service\DefaultMapper but if other defined, then all mapping settings will be ignored and not validated
                mapperClass: 
              # Optional: Default is FALSE and the same as ....Mapping.Account.persistAccounts. If one of both is TRUE then account will be persisted.
                persistAccounts: 
              # Optional: Default is FALSE but is ignored by: persistAccounts: FALSE
                persistParties: 
              # Optional: Default is FALSE. If set to TRUE, then settings for person can be omitted.
                doNotMapParties: 
              # Optional: Default is "No Redirect" :: Is usefull if you want users "accepting privacy policy" by new users. Is ignored if: "persistAccounts: FALSE" 
              # but if defined to persist and redirect then you must persist account and party by yourself.
#                redirectByNewUser:
#                  Controller: 
#                  Action:
#                  packageKey:
#                  arguments:
                Account:
                ################################################################
                # Requered: path to value in casAttributes array
                  accountidentifier: path.to.account.identifier.in.cas.attributes
                # Optional: Deafault is NULL, path to source as string expected
                  credentialsSource: path.to.credentials.source
                # Optional: Default is NULL
                  expirationDate: ''
                # Optional: Default is NULL but if is set expirationDate = periodOfValidity+creationDate
                  periodOfValidity: 183
                # Optional: Default is NULL. If is set, then checks CasManager the existency of provider name.
                # This option is ignored by: "persistAccounts: FALSE"
                  useStaticProviderName: DefaultProvider
                # Optional: Default is NULL. If is set, then checking the existency of provider name is skipped.
                # This option overrides "useProviderNameByPersistingAccount" but is ignored to by: "persistAccounts: FALSE"
                  forceUseStaticProviderName: DefaultProvider
                #
                ################################################################
                Roles:
                ################################################################
                # Description:
                #   requered:
                #     "identifier:"       Type expected: string
                #                         aim            path to value in casAttributes array.
                #                         example:       office.department for $casAttributes['office']['department']
                #   or
                #
                #     "staticIdentifier:" Type expected: string
                #                         overrides:     "identifier" 
                #                         aim:           DeafaultMapper will not look in CAS-attributes 
                #                                        and simply assign difined role to account.
                #                                        Uses role identifier from Policy.yaml for role.
                #
                #     "packageKey"        Type expected: string
                #                         aim:           Uses package key where roles are defined (see Policy.yaml).
                #
                #   optional:
                #     "rewriteRoles"      Type expected: array
                #                         depends on:    "identifier:"
                #                         ignored by:    "staticIdentifier:"
                #                         aim:           if roles by CAS-Attributes are not human readable 
                #                                        then can you rewrite them.
                ##
                # WARNING: leastways one role must be defined.
################# Example: (Note: "-" are requered)
#                  -
#                    identifier: office.department
#                    packageKey: RafaelKa.JasigPhpCas.Demo
#                    rewriteRoles: 
#                      S: Student
#                      P: Professor
#                      D: Director
#                      R: Rector
#                  -
#                    staticIdentifier: casUser
#                    packageKey: RafaelKa.JasigPhpCas.Demo
#                  -
#                    identifier: userClass
#                    packageKey: RafaelKa.JasigPhpCas.Demo
#################################################################################
                  - 
                  ##############################################################
                  # One of both options must be defined.
                    identifier: path.to.role.identifier
                  # "staticIdentifier" overrides "identifier" and uses role identifier from Policy.yaml
                  # mapper will not look in CAS-attributes and simply assign difined role to account.
                    staticIdentifier: userFromSomeSite
                  # 
                  ##
                  # requered
                    packageKey: RafaelKa.JasigPhpCas.Demo
                  ##############################################################
                  -
                    identifier: path.to.role.identifier
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
################################################################################
                ################################################################
                Party:
                ################################################################
                  Person:
                  ##############################################################
                  # is TYPO3\Party\Domain\Model\PersonName entity and properties from this.
                  # Each property is optional, like definition of PersonName class but atleast one property must be set, otherwise is name NULL @ Person entity.
                    name:
                      alias:
                      title:
                      firstName: path.to.first.name
                      middleName:
                      lastName: surnames
                      otherName:
                  # is TYPO3\Party\Domain\Model\ElectronicAddress entity and properties of this entity.
                  # Optional: Default is NULL but if set then identifier and three other (static)properties must be defined.
                    primaryElectronicAddress:
                    ############################################################
                    # Description:
                    #   requered:
                    #     "identifier:"       Type expected:.string
                    #                         aim:. . . . . .path to value in casAttributes array.
                    #     
                    #     "type:"    || "staticType:"
                    #     "usage:"   || "staticUsage:"
                    #     "approved:"|| "staticApproved:"
                    #
                    #     "***:"              Type expected: string | boolean by "approved:"
                    #                         aim:. . . . . .path to value in casAttributes array.
                    #
                    #     "static***:"        Type expected: string | boolean by "staticApproved:"
                    #                         overrides:. . ."***:"
                    # 
##################### Example1: #################################################
#                      identifier: electronicAddress.0.identifier
#                      type: electronicAddress.0.type
#                      usage: electronicAddress.0.usage
#                      approved: electronicAddress.0.approved
#################################################################################  
##################### Example2: #################################################
#                      identifier: electronicAddress.0.identifier
#                      staticType: E-mail
#                      usage: Work
#                      staticApproved: TRUE
#################################################################################
                      identifier: path.to.mail
                      staticType: E-mail
                      usage: path.to.usage
                      staticApproved: TRUE
################################################################################

                    # Not implemented currently.
                    electronicAddresses:
                    ###########################################################
                    # The same as primaryElectronicAddress: but as array
                    #
##################### Example: ##################################################
#                      -
#                        identifier: mail
#                        staticType: E-mail
#                        staticUsage: Work
#                        staticApproved: TRUE
#################################################################################
```
