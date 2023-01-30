<?php

class WC_Pledg_Constants
{
    public const PLEDG_STAGING_FRONT_URI = 'https://staging.front.ecard.pledg.co';
    public const PLEDG_PROD_FRONT_URI = 'https://front.ecard.pledg.co';

    public const PLEDG_STAGING_BACK_URI = 'https://staging.back.ecard.pledg.co/api';
    public const PLEDG_PROD_BACK_URI = 'https://back.ecard.pledg.co/api';

    public const PLEDG_CGU_INSTALLMENT_URI = 'https://pledg.co/%sconditions-generales-du-paiement-en-plusieurs-fois/';
    public const PLEDG_CGU_DEFERRED_URI = 'https://pledg.co/%sconditions-generales-du-paiement-differe/';

    public const PLEDG_PAYMENT_TYPES = [
        'installment' => 'installment',
        'deferred' => 'deferred'
    ];

    public const PLEDG_ERROR_CODES = [
        'BI_CANCELLED' => 'Not finalized',
        'BI_KO' => 'Rejected',
        'BI_SCORING_KO' => 'Rejected',
        'CARD_FILTER_KO' => 'Rejected',
        'CONNECTOR_KO' => 'Error',
        'PRIMARY_KO' => 'Customer bank refusal',
        'SCORING_KO' => 'Refused',
        'TMX_SCORING_KO' => 'Refused',
        'VCP_KO' => 'Error',
        'WORLDCHECK_FILTER_KO' => 'Refused',
        'DEFAULT' => 'Rejected',
    ];
}
