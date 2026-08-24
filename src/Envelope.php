<?php

class Envelope
{
    private static function x($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function accounts(
        array $cfg,
        string $transactionId,
        string $ixbrlBase64
    ): string {
        $p = $cfg['presenter'];
        $c = $cfg['company'];
        $f = $cfg['form'];
        $gatewayTestTag = $cfg['gateway_test'] ? '<GatewayTest>1</GatewayTest>' : '';

        $transactionId = self::x($transactionId);
        $senderId = self::x($p['sender_id']);
        $authMethod = self::x($p['auth_method']);
        $authValue = self::x($p['auth_value']);
        $email = self::x($p['email']);
        $companyNumber = self::x($c['number']);
        $companyType = self::x($c['type']);
        $companyName = self::x($c['name']);
        $companyAuthCode = self::x($c['auth_code']);
        $packageReference = self::x($f['package_reference']);
        $language = self::x($f['language']);
        $formIdentifier = self::x($f['form_identifier']);
        $submissionNumber = self::x($f['submission_number']);
        $contactName = self::x($f['contact_name']);
        $contactNumber = self::x($f['contact_number']);
        $customerReference = self::x($f['customer_reference']);
        $dateSigned = self::x($f['date_signed']);
        $dateDocument = self::x($f['date_document']);
        $filename = self::x($f['document_filename']);
        $contentType = self::x($f['document_content_type']);
        $category = self::x($f['document_category']);
        $ixbrlBase64 = self::x($ixbrlBase64);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope"
                xmlns:gt="http://www.govtalk.gov.uk/CM/core"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.govtalk.gov.uk/CM/envelope http://xmlgw.companieshouse.gov.uk/v1-0/schema/Egov_ch-v2-0.xsd">
  <EnvelopeVersion>2.0</EnvelopeVersion>
  <Header>
    <MessageDetails>
      <Class>Accounts</Class>
      <Qualifier>request</Qualifier>
      <Function>submit</Function>
      <TransactionID>{$transactionId}</TransactionID>
      {$gatewayTestTag}
    </MessageDetails>
    <SenderDetails>
      <IDAuthentication>
        <SenderID>{$senderId}</SenderID>
        <Authentication>
          <Method>{$authMethod}</Method>
          <Value>{$authValue}</Value>
        </Authentication>
      </IDAuthentication>
      <EmailAddress>{$email}</EmailAddress>
    </SenderDetails>
  </Header>
  <GovTalkDetails>
    <Keys>
      <Key Type="FormType">Accounts</Key>
    </Keys>
    <TargetDetails>
      <Organisation>Companies House</Organisation>
    </TargetDetails>
  </GovTalkDetails>
  <Body>
    <FormSubmission xmlns="http://xmlgw.companieshouse.gov.uk/Header"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://xmlgw.companieshouse.gov.uk/Header http://xmlgw.companieshouse.gov.uk/v1-0/schema/forms/FormSubmission-v2-11.xsd">
      <FormHeader>
        <CompanyNumber>{$companyNumber}</CompanyNumber>
        <CompanyType>{$companyType}</CompanyType>
        <CompanyName>{$companyName}</CompanyName>
        <CompanyAuthenticationCode>{$companyAuthCode}</CompanyAuthenticationCode>
        <PackageReference>{$packageReference}</PackageReference>
        <Language>{$language}</Language>
        <FormIdentifier>{$formIdentifier}</FormIdentifier>
        <SubmissionNumber>{$submissionNumber}</SubmissionNumber>
        <ContactName>{$contactName}</ContactName>
        <ContactNumber>{$contactNumber}</ContactNumber>
        <CustomerReference>{$customerReference}</CustomerReference>
      </FormHeader>
      <DateSigned>{$dateSigned}</DateSigned>
      <Form></Form>
      <Document>
        <Data>{$ixbrlBase64}</Data>
        <Date>{$dateDocument}</Date>
        <Filename>{$filename}</Filename>
        <ContentType>{$contentType}</ContentType>
        <Category>{$category}</Category>
      </Document>
    </FormSubmission>
  </Body>
</GovTalkMessage>
XML;
    }

    public static function status(array $cfg, string $transactionId): string
    {
        $p = $cfg['presenter'];
        $gatewayTestTag = $cfg['gateway_test'] ? '<GatewayTest>1</GatewayTest>' : '';
        $transactionId = self::x($transactionId);
        $senderId = self::x($p['sender_id']);
        $authMethod = self::x($p['auth_method']);
        $authValue = self::x($p['auth_value']);
        $email = self::x($p['email']);
        $submissionNumber = self::x($cfg['form']['submission_number']);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope"
                xmlns:gt="http://www.govtalk.gov.uk/CM/core"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.govtalk.gov.uk/CM/envelope http://xmlgw.companieshouse.gov.uk/v1-0/schema/Egov_ch-v2-0.xsd">
  <EnvelopeVersion>2.0</EnvelopeVersion>
  <Header>
    <MessageDetails>
      <Class>GetSubmissionStatus</Class>
      <Qualifier>request</Qualifier>
      <Function>read</Function>
      <TransactionID>{$transactionId}</TransactionID>
      {$gatewayTestTag}
    </MessageDetails>
    <SenderDetails>
      <IDAuthentication>
        <SenderID>{$senderId}</SenderID>
        <Authentication>
          <Method>{$authMethod}</Method>
          <Value>{$authValue}</Value>
        </Authentication>
      </IDAuthentication>
      <EmailAddress>{$email}</EmailAddress>
    </SenderDetails>
  </Header>
  <GovTalkDetails>
    <Keys>
      <Key Type="FormType">GetSubmissionStatus</Key>
    </Keys>
    <TargetDetails>
      <Organisation>Companies House</Organisation>
    </TargetDetails>
  </GovTalkDetails>
  <Body>
    <GetSubmissionStatus xmlns="http://xmlgw.companieshouse.gov.uk">
      <SubmissionNumber>{$submissionNumber}</SubmissionNumber>
      <PresenterID>{$senderId}</PresenterID>
    </GetSubmissionStatus>
  </Body>
</GovTalkMessage>
XML;
    }

    public static function ack(array $cfg, string $transactionId): string
    {
        $p = $cfg['presenter'];
        $gatewayTestTag = $cfg['gateway_test'] ? '<GatewayTest>1</GatewayTest>' : '';
        $transactionId = self::x($transactionId);
        $senderId = self::x($p['sender_id']);
        $authMethod = self::x($p['auth_method']);
        $authValue = self::x($p['auth_value']);
        $email = self::x($p['email']);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope"
                xmlns:gt="http://www.govtalk.gov.uk/CM/core"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.govtalk.gov.uk/CM/envelope http://xmlgw.companieshouse.gov.uk/v1-0/schema/Egov_ch-v2-0.xsd">
  <EnvelopeVersion>2.0</EnvelopeVersion>
  <Header>
    <MessageDetails>
      <Class>StatusAck</Class>
      <Qualifier>request</Qualifier>
      <Function>submit</Function>
      <TransactionID>{$transactionId}</TransactionID>
      {$gatewayTestTag}
    </MessageDetails>
    <SenderDetails>
      <IDAuthentication>
        <SenderID>{$senderId}</SenderID>
        <Authentication>
          <Method>{$authMethod}</Method>
          <Value>{$authValue}</Value>
        </Authentication>
      </IDAuthentication>
      <EmailAddress>{$email}</EmailAddress>
    </SenderDetails>
  </Header>
  <GovTalkDetails>
    <TargetDetails>
      <Organisation>Companies House</Organisation>
    </TargetDetails>
  </GovTalkDetails>
  <Body>
    <StatusAck xmlns="http://xmlgw.companieshouse.gov.uk" />
  </Body>
</GovTalkMessage>
XML;
    }
}
