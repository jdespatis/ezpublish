<?php
//
// Definition of eZBenchmarkrunner class
//
// Created on: <18-Feb-2004 11:56:59 >
//
// Copyright (C) 1999-2005 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file ezbenchmarkrunner.php
*/

/*!
  \class eZBenchmarkrunner ezbenchmarkrunner.php
  \brief The class eZBenchmarkrunner does

*/

class eZBenchmarkrunner
{
    /*!
     Constructor
    */
    function eZBenchmarkrunner()
    {
        $this->Results = array();
        $this->CurrentResult = false;
        $this->IsSuccessful = false;
        $this->DefaultRepeatCount = 50;
    }

    function run( &$benchmark, $display = false )
    {
        $this->Results = array();
        $this->CurrentResult = false;
        if ( is_subclass_of( $benchmark, 'ezbenchmarkunit' ) )
        {
            $markList = $benchmark->markList();
            foreach ( $markList as $mark )
            {
                $type = $this->markEntryType( $benchmark, $mark );
                if ( $type )
                {
                    $mark['type'] = $type;
                    $this->prepareMarkEntry( $benchmark, $mark );

                    $this->runMarkEntry( $benchmark, $mark );

                    $this->finalizeMarkEntry( $benchmark, $mark, $display );
                }
                else
                    $this->addToCurrentResult( $mark,
                                               "Unknown mark type for mark " . $benchmark->name() . '::' . $mark['name'] );
            }
        }
        else
        {
            eZDebug::writeWarning( "Tried to run test on an object which is not subclassed from eZBenchmarkCase",
                                   'eZBenchmarkRunner::run' );
        }
    }

    function markEntryType( $benchmark, $entry )
    {
        if ( isset( $entry['method'] ) and
             isset( $entry['object'] ) )
        {
            return 'method';
        }
        else if ( isset( $entry['function'] ) )
        {
            return 'function';
        }
        return false;
    }

    function prepareMarkEntry( &$benchmark, $entry )
    {
        $this->setCurrentMarkName( $benchmark->name() . '::' . $entry['name'] );
        $this->resetCurrentResult();
    }

    function finalizeMarkEntry( &$benchmark, $entry, $display )
    {
        $currentResult = $this->addCurrentResult();
        $this->setCurrentMarkName( false );

        if ( $display )
            $this->display( $currentResult );
    }

    function runMarkEntry( &$benchmark, $entry )
    {
        switch ( $entry['type'] )
        {
            case 'method':
            {
                $object =& $entry['object'];
                $method =& $entry['method'];
                if ( method_exists( $object, $method ) )
                {
                    if ( method_exists( $object, 'prime' ) )
                    {
                        $entry['prime_start'] = array( 'memory' => memory_get_usage(),
                                                       'time' => microtime() );
                        $object->prime( $this );
                        $entry['prime_end'] = array( 'memory' => memory_get_usage(),
                                                     'time' => microtime() );
                    }

                    $repeatCount = $this->DefaultRepeatCount;
                    if ( isset( $entry['repeat_count'] ) )
                        $repeatCount = $entry['repeat_count'];

                    $entry['start'] = array( 'memory' => memory_get_usage(),
                                             'time' => microtime() );
                    for ( $i = 0; $i < $repeatCount; ++$i )
                    {
                        $object->$method( $this, $entry['parameter'] );
                    }
                    $entry['end'] = array( 'memory' => memory_get_usage(),
                                           'time' => microtime() );

                    if ( method_exists( $object, 'cleanup' ) )
                        $object->cleanup( $this );

                    $this->processRecording( $benchmark, $entry, $repeatCount );
                }
                else
                {
                    $this->addToCurrentResult( $entry,
                                               "Method $method does not exist for mark object(" . get_class( $object ) . ")" );
                }
            } break;

            case 'function':
            {
                $function = $entry['function'];
                if ( function_exists( $function ) )
                {
                    $repeatCount = $this->DefaultRepeatCount;;
                    if ( isset( $entry['repeat_count'] ) )
                        $repeatCount = $entry['repeat_count'];

                    $entry['start'] = array( 'memory' => memory_get_usage(),
                                             'time' => microtime() );
                    for ( $i = 0; $i < $repeatCount; ++$i )
                    {
                        $function( $this, $entry['parameter'] );
                    }
                    $entry['end'] = array( 'memory' => memory_get_usage(),
                                           'time' => microtime() );

                    $this->processRecording( $benchmark, $entry, $repeatCount );
                }
                else
                {
                    $this->addToCurrentResult( $entry,
                                               "Function $function does not exist" );
                }
            } break;
        }
    }

    function processRecording( &$benchmark, &$entry, $repeatCount )
    {
        $memoryDiff = $entry['end']['memory'] - $entry['start']['memory'];
        $startTime = explode( " ", $entry['start']['time'] );
        ereg( "0\.([0-9]+)", "" . $startTime[0], $t1 );
        $startTime = $startTime[1] . "." . $t1[1];
        $endTime = explode( " ", $entry['end']['time'] );
        ereg( "0\.([0-9]+)", "" . $endTime[0], $t1 );
        $endTime = $endTime[1] . "." . $t1[1];
        $timeDiff = $endTime - $startTime;

        $entry['result'] = array( 'memory' => $memoryDiff,
                                  'time' => $timeDiff );
        $entry['normalized'] = array( 'time' => $timeDiff / $repeatCount );

        $memoryDiff = $entry['prime_end']['memory'] - $entry['prime_start']['memory'];
        $startTime = explode( " ", $entry['prime_start']['time'] );
        ereg( "0\.([0-9]+)", "" . $startTime[0], $t1 );
        $startTime = $startTime[1] . "." . $t1[1];
        $endTime = explode( " ", $entry['prime_end']['time'] );
        ereg( "0\.([0-9]+)", "" . $endTime[0], $t1 );
        $endTime = $endTime[1] . "." . $t1[1];
        $timeDiff = $endTime - $startTime;

        $entry['prime'] = array( 'memory' => $memoryDiff,
                                 'time' => $timeDiff );

        $this->addToCurrentResult( $entry );
    }

    /*!
     \virtual
     \protected
     Called whenever a test is run, can be overriden to print out the test result immediately.
    */
    function display( $result, $repeatCount )
    {
    }

    /*!
     \return an array with all the results from the last run.
    */
    function resultList()
    {
        return $this->Results;
    }

    /*!
     \protected
      Adds a result for mark \a $markName with optional message \a $message.
    */
    function addToCurrentResult( $entry, $message = false )
    {
        $markName = $entry['name'];
        if ( !is_array( $this->CurrentResult ) )
        {
             $this->CurrentResult = array( 'name' => $testName,
                                           'start' => false,
                                           'end' => false,
                                           'result' => false,
                                           'normalized' => false,
                                           'prime' => false,
                                           'messages' => array() );
        }
        $repeatCount = $this->DefaultRepeatCount;
        if ( isset( $entry['repeat_count'] ) )
            $repeatCount = $entry['repeat_count'];
        $this->CurrentResult['repeat_count'] = $repeatCount;

        if ( isset( $entry['start'] ) )
            $this->CurrentResult['start'] = $entry['start'];
        if ( isset( $entry['end'] ) )
            $this->CurrentResult['end'] = $entry['end'];
        if ( isset( $entry['result'] ) )
            $this->CurrentResult['result'] = $entry['result'];
        if ( isset( $entry['normalized'] ) )
            $this->CurrentResult['normalized'] = $entry['normalized'];
        if ( isset( $entry['prime'] ) )
            $this->CurrentResult['prime'] = $entry['prime'];

        if ( $message )
            $this->CurrentResult['messages'][] = array( 'text' => $message );
    }

    /*!
     \protected
     Adds the current result to the result list and resets the current result data.
    */
    function addCurrentResult()
    {
        if ( is_array( $this->CurrentResult ) )
            $this->Results[] = $this->CurrentResult;
        return $this->CurrentResult;
    }

    /*!
     \protected
     Resets the current result data.
    */
    function resetCurrentResult()
    {
        $this->CurrentResult = array( 'name' => $this->currentMarkName(),
                                      'messages' => array() );
    }

    /*!
     \return the name of the currently running mark or \c false if no mark.
    */
    function currentMarkName()
    {
        return $this->CurrentMarkName;
    }

    /*!
     \protected
     Sets the name of the currently running mark to \a $name.
    */
    function setCurrentMarkName( $name )
    {
        $this->CurrentMarkName = $name;
    }

    /// \privatesection
    /// An array with test results.
    var $Results;
    /// The current result
    var $CurrentResult;
    /// The name of the currently running mark or \c false
    var $CurrentMarkName;
}

?>
