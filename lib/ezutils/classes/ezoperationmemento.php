<?php
//
// Definition of eZOperationMemento class
//
// Created on: <06-���-2002 16:19:18 sp>
//
// Copyright (C) 1999-2002 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/home/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file ezoperationmemento.php
*/

/*!
  \class eZOperationMemento ezoperationmemento.php
  \brief The class eZOperationMemento does

*/

class eZOperationMemento extends eZPersistentObject
{
    /*!
     Constructor
    */
    function eZOperationMemento( $row )
    {
        $this->eZPersistentObject( $row );
    }

    function &definition()
    {
        return array( 'fields' => array( 'id' => 'ID',
                                         'memento_key' => 'MementoKey',
                                         'memento_data' => 'MementoData'
                                         ),
                      'keys' => array( 'id' ),
                      "increment_key" => "id",
                      'class_name' => 'eZOperationMemento',
                      'name' => 'ezoperation_memento' );
    }


    function &fetch( $mementoKey, $asObject = true )
    {
        if( is_array( $mementoKey ) )
        {
            $mementoKey = eZOperationMemento::createKey( $mementoKey );
        }

        return eZPersistentObject::fetchObject( eZOperationMemento::definition(),
                                                null,
                                                array( 'memento_key' => $mementoKey ),
                                                $asObject );
    }

    function &fetchList( $mementoKey, $asObject = true )
    {
        if( is_array( $mementoKey ) )
        {
            $mementoKey = eZOperationMemento::createKey( $mementoKey );
        }

        return eZPersistentObject::fetchObjectList( eZOperationMemento::definition(),
                                                null,
                                                array( 'memento_key' => $mementoKey ),
                                                null,
                                                null,
                                                $asObject );
    }

    function &create( $mementoKey, $data = array() )
    {
        if( is_array( $mementoKey ) )
        {
            $mementoKey = eZOperationMemento::createKey( $mementoKey );
        }

        $serializedData = serialize( $data );
        return new eZOperationMemento( array( 'memento_key' => $mementoKey,
                                              'memento_data' => $serializedData ) );
    }

    function createKey( $parameters )
    {
        $string = '';
        foreach ( array_keys( $parameters ) as $key )
        {
            $value =& $parameters[$key];
            $string .= $key . $value;
        }
        return md5( $string );
    }


}

?>
