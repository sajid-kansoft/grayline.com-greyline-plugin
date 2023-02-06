<?php

namespace TmtAuthstring;

class Validate
{
    /**
     * ID of transaction.
     *
     * @var integer
     */
    protected $transaction_id = 0;

    /**
     * Status of transaction.
     *
     * @var string
     */
    protected $status = '';

    /**
     * Total of transaction.
     *
     * @var integer
     */
    protected $total = 0;

    /**
     * Secret key for the channel of the transaction.
     *
     * @var string
     */
    protected $channel_secret = '';

    /**
     * Constructor.
     *
     * @param integer $transaction_id Transaction ID.
     * @param string  $status         Transaction Status.
     * @param integer $total          Transaction Total.
     * @param string  $channel_secret Channel Secret.
     */
    public function __construct($transaction_id, $status, $total, $channel_secret)
    {
        $this->transaction_id = $transaction_id;
        $this->status         = $status;
        $this->total          = $total;
        $this->channel_secret = $channel_secret;
    }

    /**
     * Test if returned hash is valid.
     *
     * @param string $hash Transaction hash.
     * @return boolean
     */
    public function isValidHash($hash)
    {
        $values       = [
            'id'        => $this->transaction_id,
            'status'    => $this->status,
            'total'     => $this->total
        ];

        $varString    = implode('&', $values);
        $authString   = hash('sha256', $varString);
        $validHash    = hash('sha256', $authString . $this->channel_secret);

        return hash_equals($hash, $validHash);
    }
}
