<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

    // Klassendefinition
    class ValetudoDevice extends IPSModule
    {
        use ValetudoCommon;

        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create()
        {
            // Diese Zeile nicht löschen.
            parent::Create();

            /* Verbinde zur GUID vom MQTT Server (Splitter)
               TX (vom Modul zum Server) {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}
               RX (vom Server zum Modul) {7F7632D9-FA40-4F38-8DEA-C83CD4325A32} */
            $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

            // Eigenschaften registrieren
            $this->RegisterPropertyString('VRE_Hostname', '');

            // Prüfen ob Variablenprofile vorhanden und diese ggf. neu anlegen
            if (IPS_VariableProfileExists('VRE.Commands')) {
                IPS_DeleteVariableProfile('VRE.Commands');
                $this->CreateModuleVariableProfile('VRE.Commands');
            } else {
                $this->CreateModuleVariableProfile('VRE.Commands');
            }
            if (IPS_VariableProfileExists('VRE.States')) {
                IPS_DeleteVariableProfile('VRE.States');
                $this->CreateModuleVariableProfile('VRE.States');
            } else {
                $this->CreateModuleVariableProfile('VRE.States');
            }
            if (IPS_VariableProfileExists('VRE.FanSpeeds')) {
                IPS_DeleteVariableProfile('VRE.FanSpeeds');
                $this->CreateModuleVariableProfile('VRE.FanSpeeds');
            } else {
                $this->CreateModuleVariableProfile('VRE.FanSpeeds');
            }

            // Anlegen der Variablen
            $this->RegisterVariableInteger('VRE_Commands', $this->Translate('Command'), 'VRE.Commands', 10);
            $this->EnableAction('VRE_Commands');
            $this->RegisterVariableInteger('VRE_States', $this->Translate('State'), 'VRE.States', 30);
            $this->RegisterVariableInteger('VRE_BatteryLevel', $this->Translate('Battery level'), '~Battery.100', 40);
            $this->RegisterVariableInteger('VRE_FanSpeeds', $this->Translate('Suction power'), 'VRE.FanSpeeds', 50);
            $this->EnableAction('VRE_FanSpeeds');

        }

        public function Destroy()
        {
            // Diese Zeile nicht löschen
            parent::Destroy();

            // Angelgte Variablenprofile löschen
            if (IPS_VariableProfileExists('VRE.Commands')) {
                IPS_DeleteVariableProfile('VRE.Commands');
            }
            if (IPS_VariableProfileExists('VRE.States')) {
                IPS_DeleteVariableProfile('VRE.States');
            }
            if (IPS_VariableProfileExists('VRE.FanSpeeds')) {
                IPS_DeleteVariableProfile('VRE.FanSpeeds');
            }
        }

        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges()
        {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();
            // Benötigt MQTT Server Instanz
            $this->RequireParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

            // Auslesen der MQTT Config
            $FullTopic = $this->ReturnMqttFullTopic();
            $this->SetReceiveDataFilter('.*' . $FullTopic . '.*');

        }

        public function ReceiveData($JSONString)
        {
      
            $DataJSON = json_decode($JSONString); // Decode: JSONString
            $this->SendDebug(__FUNCTION__ . ': DataJSON', $DataJSON, 0);

            switch ($DataJSON->DataID) {
                case '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}': // MQTT Server - RX (from Server to Modul)
                    $Buffer = $DataJSON;
                    break;
                default:
                    $this->SendDebug('Invalid Parent', KL_ERROR, 0);
                    return;
            }

            if (fnmatch('*command_status', $Buffer->Topic)) {
                $Payload = json_decode($Buffer->Payload);
                switch ($Payload->command) {
                    case 'start':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 1);
                        break;
                    case 'return_to_base':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 2);
                        break;
                    case 'stop':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 3);
                        break;
                    case 'clean_spot':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 4);
                        break;
                    case 'locate':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 5);
                        break;
                    case 'pause':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 6);
                        break;
                    case 'go_to':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 7);
                        break;
                    case 'zoned_cleanup':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 8);
                        break;
                    case 'segmented_cleanup':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 9);
                        break;
                    case 'reset_consumable':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 10);
                        break;
                    case 'load_map':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 11);
                        break;
                    case 'store_map':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 12);
                        break;
                    case 'get_destinations':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 13);
                        break;
                    case 'play_sound':
                        SetValue($this->GetIDForIdent('VRE_Commands'), 14);
                        break;
                    case 'set_fan_speed':
                        break;
                    default:
                        $this->SendDebug('VRE_Commands', 'Invalid Value: ' . $Payload->command, 0);
                        break;
                    }
            }

            if (fnmatch('*attributes', $Buffer->Topic)) {
                $Payload = json_decode($Buffer->Payload);
                if (property_exists($Payload, 'valetudo_state')) {
                    SetValue($this->GetIDForIdent('VRE_States'), $Payload->valetudo_state->id);
                }
            }

            if (fnmatch('*state', $Buffer->Topic)) {
                $Payload = json_decode($Buffer->Payload);
                if (property_exists($Payload, 'battery_level')) {
                    $this->SetValue('VRE_BatteryLevel', $Payload->battery_level);
                }
                if (property_exists($Payload, 'fan_speed')) {
                    switch ($Payload->fan_speed) {
                        case 'min':
                            SetValue($this->GetIDForIdent('VRE_FanSpeeds'), 1);
                            break;
                        case 'medium':
                            SetValue($this->GetIDForIdent('VRE_FanSpeeds'), 2);
                            break;
                        case 'high':
                            SetValue($this->GetIDForIdent('VRE_FanSpeeds'), 3);
                            break;
                        case 'max':
                            SetValue($this->GetIDForIdent('VRE_FanSpeeds'), 4);
                            break;
                        case 'mop':
                            SetValue($this->GetIDForIdent('VRE_FanSpeeds'), 5);
                            break;
                        default:
                            $this->SendDebug('VRE_FanSpeeds', 'Invalid Value: ' . $Payload->fan_speed, 0);
                            break;
                    }
                }
            }
        }

        public function SendData(string $Topic, string $Payload){
            $FullTopic = $this->ReturnMqttFullTopic();
            $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
            $Data['PacketType'] = 3;
            $Data['QualityOfService'] = 0;
            $Data['Retain'] = true;
            $Data['Topic'] = $FullTopic . '/' . $Topic;
            $Data['Payload'] = $Payload;
            $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
            $this->SendDebug(__FUNCTION__ . ': DataJSON', $DataJSON, 0);
            $this->SendDataToParent($DataJSON);
        }

        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'VRE_Commands':
                    $this->SetCommand($Value);
                    break;
                case 'VRE_FanSpeeds':
                    $this->SetFanSpeed($Value);
                    break;
                default:
                    $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                    break;
            }
        }
        
        private function SetCommand(int $Value)
        {   
    
            switch ($Value) {
                case 1:
                    $this->SendData("command", "start");
                    break;
                case 2:
                    $this->SendData("command", "return_to_base");
                    break;
                case 3:
                    $this->SendData("command", "stop");
                    break;
                case 4:
                    $this->SendData("command", "clean_spot");
                    break;
                case 5:
                    $this->SendData("command", "locate");
                    break;
                case 6:
                    $this->SendData("command", "pause");
                    break;
                case 7:
                    $this->SendData("custom_command", '{"command": "go_to"}');
                    break;
                case 8:
                    $this->SendData("custom_command", '{"command": "zoned_cleanup"}');
                    break;
                case 9:
                    $this->SendData("custom_command",'{"command": "segmented_cleanup"}');
                    break;
                case 10:
                    $this->SendData("custom_command", '{"command": "reset_consumable"}');
                    break;
                case 11:
                    $this->SendData("custom_command", '{"command": "load_map"}');
                    break;
                case 12:
                    $this->SendData("custom_command", '{"command": "store_map"}');
                    break;
                case 13:
                    $this->SendData("custom_command", '{"command": "get_destinations"}');
                    break;
                case 14:
                    $this->SendData("custom_command", '{"command": "play_sound"}');
                    break;
                default:
                    $this->SendDebug('VRE_Commands', 'Invalid Value: ' . $Value, 0);
                    break;
            }
        }
    
        private function SetFanSpeed(int $Value)
        {   
            switch ($Value) {
                case 1:
                    $this->SendData("set_fan_speed", "min");
                    break;
                case 2:
                    $this->SendData("set_fan_speed", "medium");
                    break;
                case 3:
                    $this->SendData("set_fan_speed", "high");
                    break;
                case 4:
                    $this->SendData("set_fan_speed", "max");
                    break;
                case 5:
                    $this->SendData("set_fan_speed", "mop");
                    break;
                default:
                    $this->SendDebug('VRE_FanSpeeds', 'Invalid Value: ' . $Value, 0);
                    break;
            }
        }      

        private function ReturnMqttFullTopic(){
            // Auslesen der MQTT Config
            $IpAddress = gethostbyname($this->ReadPropertyString('VRE_Hostname'));
            if (filter_var($IpAddress, FILTER_VALIDATE_IP)) {
                $HttpApiString = 'http://' . $IpAddress . '/api/' . 'mqtt_config';
                $MqttConfigJson = file_get_contents($HttpApiString);
                $MqttConfigJsonDecoded = json_decode($MqttConfigJson); // Decode: MqttConfigJson
                $MqttIdentifier = $MqttConfigJsonDecoded->identifier;
                $MqttTopicPrefix = $MqttConfigJsonDecoded->topicPrefix;
                $FullTopic = $MqttTopicPrefix . '/' . $MqttIdentifier;
                return $FullTopic;
                } else {
                    echo("$IpAddress is not a valid IP address");
                }
        }

        private function CreateModuleVariableProfile(string $Name)
        {
            switch ($Name) {
                case 'VRE.Commands':
                    $Associations = [];
                    $Associations[] = [1, $this->Translate('Start'), '', -1];
                    $Associations[] = [2, $this->Translate('Return to base'), '', -1];
                    $Associations[] = [3, $this->Translate('Stop'), '', -1];
                    $Associations[] = [4, $this->Translate('Clean spot'), '', -1];
                    $Associations[] = [5, $this->Translate('Locate'), '', -1];
                    $Associations[] = [6, $this->Translate('Pause'), '', -1];
                    $Associations[] = [7, $this->Translate('Go to'), '', -1];
                    $Associations[] = [8, $this->Translate('Zone cleanup'), '', -1];
                    $Associations[] = [9, $this->Translate('Segment cleanup'), '', -1];
                    $Associations[] = [10, $this->Translate('Reset consumable'), '', -1];
                    $Associations[] = [11, $this->Translate('Load Map'), '', -1];
                    $Associations[] = [12, $this->Translate('Store Map'), '', -1];
                    $Associations[] = [13, $this->Translate('Get destinations'), '', -1];
                    $Associations[] = [14, $this->Translate('Play sound'), '', -1];
                    $this->RegisterProfileIntegerEx('VRE.Commands', 'Execute', '', '', $Associations);
                    break;
                case 'VRE.States':
                    $Associations = [];
                    $Associations[] = [5, $this->Translate('Cleaning'), '', -1];
                    $Associations[] = [7, $this->Translate('Cleaning'), '', -1];
                    $Associations[] = [11, $this->Translate('Cleaning'), '', -1];
                    $Associations[] = [16, $this->Translate('Cleaning'), '', -1];
                    $Associations[] = [10, $this->Translate('Paused'), '', -1];
                    $Associations[] = [2, $this->Translate('Idle'), '', -1];
                    $Associations[] = [3, $this->Translate('Idle'), '', -1];
                    $Associations[] = [6, $this->Translate('Returning'), '', -1];
                    $Associations[] = [15, $this->Translate('Returning'), '', -1];
                    $Associations[] = [8, $this->Translate('Docked'), '', -1];
                    $Associations[] = [9, $this->Translate('Error'), '', -1];
                    $Associations[] = [12, $this->Translate('Error'), '', -1];
                    $Associations[] = [17, $this->Translate('Zone cleanup'), '', -1];
                    $Associations[] = [18, $this->Translate('Segment cleanup'), '', -1];
                    $this->RegisterProfileIntegerEx('VRE.States', 'Information', '', '', $Associations);
                    break;
                case 'VRE.FanSpeeds':
                    $Associations = [];
                    $Associations[] = [1, $this->Translate('Minimum'), '', -1];
                    $Associations[] = [2, $this->Translate('Medium'), '', -1];
                    $Associations[] = [3, $this->Translate('High'), '', -1];
                    $Associations[] = [4, $this->Translate('Maximum'), '', -1];
                    $Associations[] = [5, $this->Translate('Low level (wipe)'), '', -1];
                    $this->RegisterProfileIntegerEx('VRE.FanSpeeds', 'Intensity', '', '', $Associations);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'Invalid Value', 0);
                    break;
            }
        }
    }
