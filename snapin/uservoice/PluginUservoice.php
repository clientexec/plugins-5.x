<?php

require_once 'modules/admin/models/SnapinPlugin.php';

/**
* Based on https://developer.uservoice.com/downloads/code/sso/uservoice_sso.php
*/
class PluginUservoice extends SnapinPlugin
{

  public function getVariables() {
    $variables = array(
      /*T*/'Plugin Name'/*/T*/       => array(
          'type'        => 'hidden',
          'description' => /*T*/''/*/T*/,
          'value'       => 'UserVoice SSO Integration',
      ),
      'Public Description'       => array(
          'type'        => 'text',
          'description' => 'Description to be seen by public',
          'value'       => 'Help us improve ClientExec by giving us your suggestions',
      ),
      /*T*/'Subdomain'/*/T*/       => array(
          'type'        => 'text',
          'description' => 'Your UserVoice subdomain. For example if your UserVoice URL is http://foobar.uservoice.com, then your subdomain is foobar.'
      ),
      lang('SSO key')       => array(
          'type'        => 'text',
          'description' => 'Your UserVoice account SSO key. You can get it from the User Authentication portion in the UserVoice General Settings'
      ),
    );
    return $variables;
  }

  public function init()
  {
      $this->settingsNotes = lang('When enabled this gives your users the ability to sign into your UserVoice account through your ClientExec\'s instance. To use it make sure your UserVoice account supports Single Sign-On (SSO) and that it\'s properly configured under the User Authentication section in your UserVoice account.');

      $this->addMappingForPublicMain("view", "UserVoice", 'Integrate Clientexec with Uservoice in Public Home', 'icon-bullhorn', 'margin: 2px;');
      $this->addMappingForTopMenu('public', '', 'view', 'UserVoice', 'Integrate Clientexec with UserVoice In Top Menu');
  }

  public function view() {

    $this->overrideTemplate = true;

    $returnUrl = '/';

    if (@$this->session->redirectUserVoice) {
      $returnUrl = $this->session->redirectUserVoice;
      unset($this->session->redirectUserVoice);
    }

    $url = (CE_Lib::isHttps()? 'https://' : 'http://')
      .$this->settings->get('plugin_uservoice_Subdomain')
      . ".uservoice.com$returnUrl";

    if (!$this->user->isAnonymous()) {
      // don't wanna have an "Anonymous"-labeled user in uservoice
      $url .= '?sso=' . $this->getUrl($this->user);
    }

    return "<script>location.href = '$url';</script>";
  }

  private function getUrl(User $user) {

    $uservoice_subdomain = $this->settings->get('plugin_uservoice_Subdomain');
    $sso_key = $this->settings->get('plugin_uservoice_SSO key');

    $salted = $sso_key . $uservoice_subdomain;
    $hash = hash('sha1',$salted,true);
    $saltedHash = substr($hash,0,16);
    $iv = "OpenSSL for Ruby";

    $user_data = array(
      "guid" => $user->getId(),
      "expires" => "2031-01-12 22:56:42",
      "display_name" => $user->getFullName(),
      "email" => $user->getEmail(),
      'owner' => 'deny',
      'admin' => 'deny',
      "updates" => 'true',
      "comment_updates" => 'true',
    );

    $data = json_encode($user_data);

    // double XOR first block
    for ($i = 0; $i < 16; $i++)
    {
     $data[$i] = $data[$i] ^ $iv[$i];
    }

    $pad = 16 - (strlen($data) % 16);
    $data = $data . str_repeat(chr($pad), $pad);

    $cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128,'','cbc','');
    mcrypt_generic_init($cipher, $saltedHash, $iv);
    $encryptedData = mcrypt_generic($cipher,$data);
    mcrypt_generic_deinit($cipher);

    $encryptedData = urlencode(base64_encode($encryptedData));

    return $encryptedData;
  }
}

