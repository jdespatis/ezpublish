<?php
//
// Definition of eZTemplateDefFunction class
//
// Created on: <28-Feb-2005 16:03:02 vs>
//
// Copyright (C) 1999-2006 eZ systems as. All rights reserved.
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

/*!
  \class eZTemplateDefFunction eztemplatedeffunction.php
  \ingroup eZTemplateFunctions
  \brief Allows to define/undefine template variables in any place.

  This class allows to execute on of two or more code pieces depending
  on a condition.

  Syntax:
\code
    {def $var1=<value1> [$var2=<value2> ...]}
\endcode

  Example:
\code
    {def $i=10 $j=20}
    {def $s1='hello' $s2='world'}
    ...
    {set $i=$i+1}
    ...
    {undef $i}
    {undef $s1 $s2}
    {undef}
\endcode
*/

define ( 'EZ_TEMPLATE_DEF_FUNCTION_NAME',   'def'   );
define ( 'EZ_TEMPLATE_UNDEF_FUNCTION_NAME', 'undef' );
class eZTemplateDefFunction
{
    /*!
     * Returns an array of the function names, required for eZTemplate::registerFunctions.
     */
    function &functionList()
    {
        $functionList = array( EZ_TEMPLATE_DEF_FUNCTION_NAME, EZ_TEMPLATE_UNDEF_FUNCTION_NAME );
        return $functionList;
    }

    /*!
     * Returns the attribute list which is 'delimiter', 'elseif' and 'else'.
     * key:   parameter name
     * value: can have children
     */
    function attributeList()
    {
        return array();
    }


    /*!
     * Returns the array with hits for the template compiler.
     */
    function functionTemplateHints()
    {
        return array( EZ_TEMPLATE_DEF_FUNCTION_NAME   => array( 'parameters' => true,
                                                                'static' => false,
                                                                'transform-parameters' => true,
                                                                'tree-transformation' => true ),
                      EZ_TEMPLATE_UNDEF_FUNCTION_NAME => array( 'parameters' => true,
                                                                'static' => false,
                                                                'transform-parameters' => true,
                                                                'tree-transformation' => true ) );
    }

    /*!
     * Compiles the function into PHP code.
     */
    function templateNodeTransformation( $functionName, &$node,
                                         &$tpl, &$parameters, $privateData )
    {
        $undef = ( $functionName == 'undef' );
        $newNodes = array();

        if ( !$parameters )
        {
            if ( !$undef )
                // prevent execution of the function in processed mode
                return array( eZTemplateNodeTool::createCodePieceNode( "// an error occured in $functionName" ) );

            // {undef} called w/o arguments => destroy all local variables
            $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "// undef all" );
            $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "\$tpl->unsetLocalVariables();" );
            return $newNodes;
        }

        $nodePlacement = eZTemplateNodeTool::extractFunctionNodePlacement( $node );
        foreach ( array_keys( $parameters ) as $parameterName )
        {
            $parameterData = $parameters[$parameterName];
            if ( $undef )
            {
                $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "// undef \$$parameterName" );
                // generates "$tpl->unsetLocalVariable();"
                $newNodes[] = eZTemplateNodeTool::createVariableUnsetNode( array( $namespaceValue = false,
                                                                                  $scope = EZ_TEMPLATE_NAMESPACE_SCOPE_LOCAL,
                                                                                  $parameterName ),
                                                                           array( 'remember_set' => false, 'local-variable' => true ) );
            }
            else
            {
                $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "// def \$$parameterName" );
                // generates "$tpl->setLocalVariable();"
                $newNodes[] = eZTemplateNodeTool::createVariableNode( false, $parameterData, $nodePlacement, array( 'local-variable' => true ),
                                                                      array( $namespaceValue = false, $scope = EZ_TEMPLATE_NAMESPACE_SCOPE_LOCAL, $parameterName ),
                                                                      $onlyExisting = false, $overwrite = true, false, $rememberSet = false );
            }
        }

        return $newNodes;
    }

    /*!
     * Actually executes the function (in processed mode).
     */
    function process( &$tpl, &$textElements, $functionName, $functionChildren, $functionParameters, $functionPlacement, $rootNamespace, $currentNamespace )
    {
        $undef = ( $functionName == EZ_TEMPLATE_UNDEF_FUNCTION_NAME ) ? true : false;

        if ( $undef && !count( $functionParameters ) ) // if {undef} called w/o arguments
        {
            // destroy all variables defined in the current template using {def}
            $tpl->unsetLocalVariables();
        }

        foreach ( array_keys( $functionParameters ) as $key )
        {
            $varName  = $key;
            $param    = $functionParameters[$varName];
            $varValue = $tpl->elementValue( $param, $rootNamespace, $currentNamespace, $functionPlacement );


            if ( $undef ) // {undef}
            {
                if ( !$tpl->hasLocalVariable( $varName, $rootNamespace ) )
                    $tpl->warning( EZ_TEMPLATE_UNDEF_FUNCTION_NAME, "Variable '$varName' is not defined with {def}." );
                else
                    $tpl->unsetLocalVariable( $varName, $rootNamespace );

            }
            else // {def}
            {
                if ( $tpl->hasVariable( $varName, $rootNamespace ) ) // if the variable already exists
                {
                    // we don't create new variable but just assign value to the existing one.
                    $tpl->warning( EZ_TEMPLATE_DEF_FUNCTION_NAME, "Variable '$varName' is already defined." );
                    $tpl->setVariable( $varName, $varValue, $rootNamespace );
                }
                else
                    // create a new local variable and assign a value to it.
                    $tpl->setLocalVariable( $varName, $varValue, $rootNamespace );

            }
        }
    }

    /*!
     * Returns false, telling the template parser that the function cannot have children.
     */
    function hasChildren()
    {
        return false;
    }
}

?>
