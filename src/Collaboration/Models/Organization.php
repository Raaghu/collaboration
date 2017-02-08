<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Persist\Exception\ObjectStateException;

/**
 * @tableName organization
 * @prefix Organization
 */
class Organization extends Account {
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName ACCOUNT_ID
     * @type bigint
     * @reference
     * @get
     */
    private $accountId = null;

    /**
     * @columnName NAME
     * @type varchar
     * @set
     * @get
     */
    private $name = null;

    /**
     * @columnName DOI
     * @type date
     * @set
     * @get
     */
    private $doi = null;

    /**
     * @columnName TYPE
     * @type varchar
     * @set
     * @get
     */
    private $type = null;

    /**
     * @columnName PARENT_ID
     * @type bigint
     * @set
     * @get
     */
    private $parentId = null;


    function __construct($id = null, $accountName = null){
        $this->id = $id;
        parent::__construct($accountName);
    }

    /**
     * @param array $data
     * @return Organization
     * 
     * @access inherit
     */
    static function create($data){
        return parent::create($data);
    }

    /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return Organization[]
     * 
     * @access inherit
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    function setAttribute($name,$value){
        return parent::setAttribute($name, $value);
    }

    /**
     * @access inherit
     */
    function setAttributes($args){
        unset($args["parentId"]);
        return parent::setAttributes($args);
    }

    function getAttribute($name){
    	return parent::getAttribute($name);
    }

    function getAttributes($args){
        $attributes = parent::getAttributes($args);
        if(isset($attributes["parentId"])){
        	unset($attributes["parentId"]);
        }
        return $attributes;
    }
    
    /**
     * @access inherit
     */
    function delete(){
    	return parent::delete();
    }
    
    // child organization manupulations //
    
    /**
     * @return Organization[]
     */
    function getChildren(){
    	return static::find(array("parentId"=>$this->id));
    }
    
    /**
     * @return Organization|NULL
     */
    function getParent(){
    	if($this->parentId != null){
    		return new Organization($this->parentId);
    	}else{
    		return null;
    	}
    }
    
    /**
     * @return Organization[]
     */
    function getAllParents(){
    	$parent = $this->getParent();
    	if($parent == null){
    		return array();
    	}
    	$allParents = $parent->getAllParents();
    	array_push($allParents, $parent);
    	return $allParents;
    }
    

    /**
     * @param Organization $parentObj
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    private function addToParent($parentObj){
        $parentId = $parentObj->getAttribute("id");
        if($this->parentId != $parentId){
            if($this->parentId != null){
            	throw new BadInputException("This organization is already a child of another organization with id ".$this->parentId);
            }
            parent::setAttributes(array("parentId"=>$parentId));
        }
    }

    /**
     * @param Organization $parentObj
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    private function removeFromParent($parentObj){
        if($this->parentId != $parentObj->getAttribute("id")){
            $parentName = $parentObj->getAttribute("name");
            $thisName   = $this->name;
            throw new BadInputException("Organization $thisName is not a child of $parentName");
        }
        parent::setAttributes(array("parentId"=>array(self::OPERATOR_EQUAL=>null)));
    }


    /**
     * @param Organization[] $children
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addChildren($children){
        if(!is_array($children)) throw new BadInputException("$children is not array");
        parent::UpdateAccess(); // explicitly check for update access 
        try{
            TransactionManager::startTransaction();
            foreach($children as $child){
                $child->addToParent($this);
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    /**
     * @param Organization[] $children
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removeChildren($children){
        if(!is_array($children)) throw new BadInputException("$children is not array");
        parent::UpdateAccess(); // explicitly check for update access
        try{
            TransactionManager::startTransaction();
            foreach($children as $child){
                $child->removeFromParent();
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }
    
    
    // connected people manupulations //
    
    /**
     * @throws Exception
     * @return Person[]
     */
    function getPeople(){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	try{
    		TransactionManager::startTransaction(null,true);
    		$organizationPersonObjs = OrganizationPerson::find(array("organizationId"=>$this->id));
    		$personIds = array();
    		foreach ($organizationPersonObjs as $organizationPersonObj){
    			$personIds[] = $organizationPersonObj->getAttribute("personId");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Person::find(array("id"=>array(self::OPERATOR_IN=>$personIds)));
    }

    /**
     * @param Person[] $people
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addPeople($people){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
        if(!is_array($people)) throw new BadInputException("$people is not array");
        parent::UpdateAccess(); // explicitly check for update access
        try{
            TransactionManager::startTransaction(null,true);
            foreach($people as $person){
                OrganizationPerson::create(array(
                		"organizationId"=>$this->id,
                		"personId"=>$person->getAttribute("id")
                ));
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    /**
     * @param Person[] $people
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removePeople($people){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
        if(!is_array($people)) throw new BadInputException("$people is not array");
        parent::UpdateAccess(); // explicitly check for update access
        try{
            TransactionManager::startTransaction(null,true);
            foreach($people as $person){
                $organizationPerson = new OrganizationPerson($this->id,$person->getAttribute("id"));
                $organizationPerson->delete();
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }


}
?>