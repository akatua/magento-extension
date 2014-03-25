<?php

class Akatua_Checkout_Block_Redirect extends Mage_Core_Block_Abstract {
    protected function _toHtml() {
        $Akatua = Mage::getModel('Akatua/Redirect');

        $form = new Varien_Data_Form();
        $form->setAction($Akatua->getAkatuaUrl())
            ->setId('pay')
            ->setName('pay')
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($Akatua->getCheckoutFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }

        $html = '<html><body>';
        $html.= '<center>Redirecting you to the payment page...</center>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("pay").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }
}
