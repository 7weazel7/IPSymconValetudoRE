<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

trait ValetudoREMQTTHelper
{
    protected function MsgBox(string $Message): void
    {
        $this->UpdateFormField('MsgText', 'caption', $Message);

        $this->UpdateFormField('MsgBox', 'visible', true);
    }
    protected function GetParent($instanceID)
    {
        $instance = IPS_GetInstance($instanceID);

        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : 0;
    }
}