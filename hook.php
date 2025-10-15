<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

function plugin_singlesignon_display_login() {
   global $CFG_GLPI;

   $signon_provider = new PluginSinglesignonProvider();

   $condition = '`is_active` = 1';
   if (version_compare(GLPI_VERSION, '9.4', '>=')) {
      $condition = [$condition];
   }
   $rows = $signon_provider->find($condition);

   $html = [];

   foreach ($rows as $row) {
      $query = [];

      if (isset($_REQUEST['redirect'])) {
         $query['redirect'] = $_REQUEST['redirect'];
      }

      $url = PluginSinglesignonToolbox::getCallbackUrl($row['id'], $query);
      $isDefault = PluginSinglesignonToolbox::isDefault($row);
      if ($isDefault && !isset($_GET["noAUTO"])) {
         Html::redirect($url);
         return;
      }
      $html[] = PluginSinglesignonToolbox::renderButton($url, $row);
   }

   if (!empty($html)) {
      echo '<div class="singlesignon-box">';
      echo implode(" \n", $html);
      echo PluginSinglesignonToolbox::renderButton('#', ['name' => __('GLPI')], 'vsubmit old-login');
      echo '</div>';
      ?>
      <style>
         #display-login .singlesignon-box span {
            display: inline-block;
            margin: 5px;
         }

         #display-login .singlesignon-box .old-login {
            display: none;
         }

         #boxlogin .singlesignon-box span {
            display: block;
         }

         #boxlogin .singlesignon-box .vsubmit {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.3em !important;
            text-align: center;
            box-sizing: border-box;
         }

         #boxlogin .singlesignon-box .vsubmit img {

            vertical-align: sub;
         }
      </style>
      <script type="text/javascript">
         $(document).ready(function() {

            // On click, open a popup
            $(document).on("click", ".singlesignon.oauth-login.popup", function(e) {
               e.preventDefault();

               var url = $(this).attr("href");
               var left = ($(window).width() / 2) - (600 / 2);
               var top = ($(window).height() / 2) - (800 / 2);
               var newWindow = window.open(url, "singlesignon", "width=600,height=800,left=" + left + ",top=" + top);
               if (window.focus) {
                  newWindow.focus();
               }
            });

      <?php if (version_compare(GLPI_VERSION, '10', '>=')) : ?>
               var $boxButtons = $('.singlesignon-box');

               $boxButtons.parent().hide();

               var $line = $boxButtons.prev('hr');
               if ($line.length) {
                  $line.remove();
               }

               var $row = $boxButtons.closest('.row');
               var $boxLogin = $row.find('div:eq(0)');

               $boxButtons.addClass('col-md-5 text-center');
               $boxButtons.prependTo($row);

               $boxButtons.find('span').addClass("row mb-2");
               $boxButtons.find('span a').addClass("col-md-12");

               $boxLogin.hide();

               $(document).on("click", ".singlesignon.old-login", function(e) {
                  e.preventDefault();
                  $boxButtons.slideUp(function() {
                     $boxLogin.slideDown(function() {
                        $boxLogin.find(':input:eq(0)').focus();
                     });
                  });
               });

               var $backLogin = $('<label />', {
                  css: {
                     cursor: 'pointer'
                  },
                  text: "<< " + <?php echo json_encode(__('Back')) ?>,
               }).prependTo($boxLogin);

               $backLogin.on('click', function(e) {
                  e.preventDefault();
                  $boxLogin.slideUp(function() {
                     $boxButtons.slideDown();
                  });
               });

            <?php else : ?>
               var $boxLogin = $('#boxlogin');

               var $form = $boxLogin.find('form');
               var $boxButtons = $('.singlesignon-box');

               // Move the buttons to before form
               $boxButtons.prependTo($boxLogin);
               $boxButtons.find('span').addClass('login_input');

               // Show old form
               $(document).on("click", ".singlesignon.old-login", function(e) {
                  e.preventDefault();
                  $boxButtons.slideUp();
                  $form.slideDown(function() {
                     $('#login_name').focus();
                  });
               });

               var $line = $('<p />', {
                  class: 'login_input'
               }).prependTo($form);

               var $backLogin = $('<label />', {
                  css: {
                     cursor: 'pointer'
                  },
                  text: "<< " + <?php echo json_encode(__('Back')) ?>,
               }).appendTo($line);

               $backLogin.on('click', function(e) {
                  e.preventDefault();
                  $boxButtons.slideDown();
                  $form.slideUp();
               });

               $form.hide();
            <?php endif; ?>
         });
      </script>
      <?php
   }
}

function plugin_singlesignon_install() {
   /* @var $DB DB */
   global $DB;

   $currentVersion = '0.0.0';

   $default = [];

   $current = Config::getConfigurationValues('singlesignon');

   if (isset($current['version'])) {
      $currentVersion = $current['version'];
   }

   foreach ($default as $key => $value) {
      if (!isset($current[$key])) {
         $current[$key] = $value;
      }
   }

   Config::setConfigurationValues('singlesignon', $current);

   $providersTable = 'glpi_plugin_singlesignon_providers';
   $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';

   $migration = new Migration(PLUGIN_SINGLESIGNON_VERSION);

   if (!$DB->tableExists($providersTable)) {
      $DB->doQuery(
         "CREATE TABLE `$providersTable` (
            `id`                         INT NOT NULL AUTO_INCREMENT,
            `is_default`                 TINYINT(1) NOT NULL DEFAULT '0',
            `popup`                      TINYINT(1) NOT NULL DEFAULT '0',
            `split_domain`               TINYINT(1) NOT NULL DEFAULT '0',
            `authorized_domains`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `type`                       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name`                       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_id`                  VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_secret`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `scope`                      VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `extra_options`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_authorize`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_access_token`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_resource_owner_details` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `is_active`                  TINYINT(1) NOT NULL DEFAULT '0',
            `use_email_for_login`        TINYINT(1) NOT NULL DEFAULT '0',
            `split_name`                 TINYINT(1) NOT NULL DEFAULT '0',
            `is_deleted`                 TINYINT(1) NOT NULL DEFAULT '0',
            `comment`                    TEXT COLLATE utf8mb4_unicode_ci,
            `date_mod`                   TIMESTAMP NULL DEFAULT NULL,
            `date_creation`              TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `date_mod` (`date_mod`),
            KEY `date_creation` (`date_creation`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
      );
   } else {
      $migration->addField($providersTable, 'is_default', 'bool');
      $migration->addField($providersTable, 'popup', 'bool');
      $migration->addField($providersTable, 'split_domain', 'bool');
      $migration->addField(
         $providersTable,
         'authorized_domains',
         'string',
         [
            'nodefault' => true,
            'null'      => true,
         ]
      );
      $migration->addField($providersTable, 'use_email_for_login', 'bool');
      $migration->addField($providersTable, 'split_name', 'bool');
   }

   if (version_compare($currentVersion, '1.2.0', '<')) {
      $migration->addField(
         $providersTable,
         'picture',
         'string',
         [
            'nodefault' => true,
            'null'      => true,
         ]
      );
      $migration->addField(
         $providersTable,
         'bgcolor',
         "varchar(7)",
         [
            'nodefault' => true,
            'null'      => true,
         ]
      );
      $migration->addField(
         $providersTable,
         'color',
         "varchar(7)",
         [
            'nodefault' => true,
            'null'      => true,
         ]
      );
   }

   if (version_compare($currentVersion, '1.3.0', '<') && !$DB->tableExists($providersUsersTable)) {
      $DB->doQuery(
         "CREATE TABLE `$providersUsersTable` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `plugin_singlesignon_providers_id` INT NOT NULL DEFAULT '0',
            `users_id` INT NOT NULL DEFAULT '0',
            `remote_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_singlesignon_providers_id`,`users_id`),
            UNIQUE KEY `unicity_remote` (`plugin_singlesignon_providers_id`,`remote_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
      );
   }

   if (!countElementsInTable('glpi_displaypreferences', ['itemtype' => 'PluginSinglesignonProvider'])) {
      $preferences = [
         ['itemtype' => 'PluginSinglesignonProvider', 'num' => 2, 'rank' => 1, 'users_id' => 0],
         ['itemtype' => 'PluginSinglesignonProvider', 'num' => 3, 'rank' => 2, 'users_id' => 0],
         ['itemtype' => 'PluginSinglesignonProvider', 'num' => 5, 'rank' => 4, 'users_id' => 0],
         ['itemtype' => 'PluginSinglesignonProvider', 'num' => 6, 'rank' => 5, 'users_id' => 0],
         ['itemtype' => 'PluginSinglesignonProvider', 'num' => 10, 'rank' => 6, 'users_id' => 0],
      ];

      foreach ($preferences as $preference) {
         $DB->insert('glpi_displaypreferences', $preference);
      }
   }

   $migration->executeMigration();

   $current['version'] = PLUGIN_SINGLESIGNON_VERSION;
   Config::setConfigurationValues('singlesignon', $current);

   return true;
}

function plugin_singlesignon_uninstall() {
   global $DB;

   $config = new Config();
   $condition = "`context` LIKE 'singlesignon%'";
   if (version_compare(GLPI_VERSION, '9.4', '>=')) {
      $condition = [$condition];
   }
   $rows = $config->find($condition);

   foreach ($rows as $id => $row) {
      $config->delete(['id' => $id]);
   }

   $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';
   $providersTable = 'glpi_plugin_singlesignon_providers';

   if ($DB->tableExists($providersUsersTable)) {
      $DB->dropTable($providersUsersTable, true);
   }

   if ($DB->tableExists($providersTable)) {
      $DB->dropTable($providersTable, true);
   }

   return true;
}
