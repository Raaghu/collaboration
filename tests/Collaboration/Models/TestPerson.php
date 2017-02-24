<?php
namespace PhpPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Persist\MySql;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Collaboration\Models\Organization;

class TestPerson extends TestBase {
	
	private $personCreator = null;
	private $personForTest = null;
	
	function setUp(){
		parent::setUp();
	
		$personCreator = null;
		$personForTest = null;
	    TransactionManager::executeInTransaction(function() use (&$personCreator,&$personForTest){
			$personCreator = Person::create(array("accountName"=>"personCreator1","firstName"=>"person Creator"));
			$loginDetails = LoginDetails::create(array("personId"=>$personCreator->getAttribute('id'),"loginName"=>"personCreator1","password"=>"personCreator1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			$personCreator->addRoles(array(new Role(null,'personCreator'),new Role(null,'orgCreator')));
				
			$personForTest = Person::create(array("accountName"=>"personForTest","firstName"=>"person For Test"));
			$loginDetails = LoginDetails::create(array("personId"=>$personForTest->getAttribute('id'),"loginName"=>"personForTest1","password"=>"personForTest1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
				
		},array(),true);
	
		$this->personCreator = $personCreator;
		$this->personForTest = $personForTest;
	}
	
	
	function testConstructor(){
		/**
		 * data
		 */
		$person = null;
		 
		TransactionManager::executeInTransaction(function () use (&$person){
			$person = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		},array(),true);
			 
		// construct without session
		$isException = false;
		try{
			new Person(null,'testPerson1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		$isException = false;
		try{
			new Person($person->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$person1 = new Person(null,'testPerson1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($person->getAttribute('firstName'), $person1->getAttribute('firstName'));

		$isException = false;
		try{
			$person2 = new Person($person->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($person->getAttribute('firstName'), $person2->getAttribute('firstName'));
	}
	
	function testCreate(){
		// create without session
		$isException = false;
		try{
			Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// create with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$person1 = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testPerson1", $person1->getAttribute("accountName"));
		parent::assertEquals("test Person 1", $person1->getAttribute("firstName"));
	
		// clean session and login as ordinary person
		$this->login('personForTest1', 'personForTest1');
	
		// create with session but not in personCreator role
		$isException = false;
		try{
			$person2 = Person::create(array('firstName'=>"test Person 2",'accountName'=>'testPerson2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// clean session and login as person creator
		$this->login('personCreator1', 'personCreator1');
	
		// create with session in personCreator role
		$isException = false;
		try{
			$person2 = Person::create(array('firstName'=>"test Person 2",'accountName'=>'testPerson2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testPerson2", $person2->getAttribute("accountName"));
		parent::assertEquals("test Person 2", $person2->getAttribute("firstName"));
	
	
		// create with complete data
		$person3 = Person::create(array(
				"firstName"  => "Test",
				"middleName" => "Person",
				"lastName"   => "3",
				"dob"        => MySql::getMysqlDate("01st Jan 1987"),
				"gender"     => Person::GENDER_MALE,
				"contact"    => array(
						"emailId" => "testPerson3@gmail.com",
						"phoneNo" => "1234567891",
						"address" => "#1,2nd cross, 3rd main, JP Nagar , Bangalore - 560078"
				),
				"accountName"=>"testPerson3"
		));
		parent::assertEquals("Test", $person3->getAttribute('firstName'));
		parent::assertEquals("Person", $person3->getAttribute('middleName'));
		parent::assertEquals("3", $person3->getAttribute('lastName'));
		parent::assertEquals("1987-01-01", $person3->getAttribute('dob'));
		parent::assertEquals(Person::GENDER_MALE, $person3->getAttribute('gender'));
		parent::assertEquals(array(
						"emailId" => "testPerson3@gmail.com",
						"phoneNo" => "1234567891",
						"address" => "#1,2nd cross, 3rd main, JP Nagar , Bangalore - 560078"
				), $person3->getAttribute('contact'));
		parent::assertEquals("testPerson3", $person3->getAttribute('accountName'));
		
	}
	
	function testFind(){
		// find without session
		
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>"person For Test"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(2,$people);

		// find with person creator session
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$people);
		parent::assertEquals($this->personCreator->getAttribute('firstName'), $people[0]->getAttribute('firstName'));
		
		// create an organization and add personForTest
		$organization = Organization::create(array("accountName"=>"myOrg1","name"=>"My Org 1"));
		$organization->addPeople(array($this->personForTest));
		
		// find with person creator session + some members in the his organization
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(2,$people);
	}
	
	function testUpdate(){
		
	}
	
	
}