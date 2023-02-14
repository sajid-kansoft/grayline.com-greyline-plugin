<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class TagibleProduct
{

    protected $cacheTagible;
    protected $xmlNode;
    protected $tourId;
    protected $node;
    public $licenseText;
    public $licenseUrl;
    public $ownerName;
    public $mainImage;
    public $ownerUrl;
    public $tagibleSuccess = false;


    public function __construct(CacheTagible $cacheTagible, $tourId)
    {
        $this->cacheTagible = $cacheTagible;
        $this->tourId = (int)$tourId;
        $xml = $this->cacheTagible->getXml();
        // First we need to get the tagible xml feed.
        if ($xml && $tourId > 0) {
            // Next we need to get the node for the tour id
            $this->node = $this->returnNode($xml, $tourId);
            if ($this->node) {
                $this->mainImage = trim((string)$this->returnNodeChild('url'));
                $this->ownerName = (string)$this->returnNodeChild('owner_name');
                $ownerUrl = (string)$this->returnNodeChild('owner_url');
                $this->ownerUrl = $ownerUrl ? $ownerUrl : null;
                $this->licenseText = (string)$this->returnNodeChild('license')->{'Text'};
                $this->licenseUrl = (string)$this->returnNodeChild('license')->{'URL'};
                $this->tagibleSuccess = true;
                $this->tagibleSuccess = $this->mainImage ? true : false;
            }
            else {
                $this->tagibleSuccess = false;
            }
            if ($this->mainImage == '') {
                $this->tagibleSuccess = false;
            }
        }
        else {
            $this->tagibleSuccess = false;
        }
    }

    protected function returnNode($xml, $id)
    {
        $query = "//*[tour_id='$id']";
        $node = $xml->result->xpath($query);
        return $node;
    }

    protected function returnNodeChild($field)
    {
        $val = $this->node[0]->{$field};
        return $val;
    }

}