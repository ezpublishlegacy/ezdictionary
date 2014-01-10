<?php
namespace MakingWaves\ezDictionary;

/**
 * Class contains the logic for dictionary template operator
 */
class Dictionary
{
    const OPERATOR_NAME = 'dictionary';
    const EXTENSION_NAME = 'eZDictionary';
    private $operators;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->operators = array( self::OPERATOR_NAME );
    }

    /**
     * Method generates an array of dictionary items basing on given array of nodes
     * @param array $nodes
     * @return array
     */
    private function generateDictionary( $nodes )
    {
        $dictionary = array();
        $title_attr = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'WordAttribute' );
        $desc_attr = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'DescriptionAttribute' );

        if ( empty( $nodes ) )
        {
            $this->printError( 'There are no nodes which matches the dictionary settings. Please check the extension configuration (ezdictionary.ini).' );
            return $dictionary;
        }

        foreach ( $nodes as $node )
        {
            $data_map = $node->dataMap();
            if ( isset( $data_map[$title_attr] ) && isset( $data_map[$desc_attr] ) )
            {
                $dictionary[$data_map[$title_attr]->attribute( 'content' )] = $data_map[$desc_attr]->attribute( 'content' );
            }
            else
            {
                $this->printError( 'Node ' . $node->attribute( 'node_id' ) . ' (class "' . $node->attribute( 'class_identifier' )
                                   . '") doesn\'t have at least one of following attributes: "' . $title_attr . '", "' . $desc_attr . '"' );
            }
        }

        return $dictionary;
    }

    /**
     * Method fetches the nodes by names defined in TemplateOperator[Classes] which are children of TemplateOperator[ParentNodes]
     * @return array
     */
    private function getWordNodes()
    {
        $parents = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'ParentNodes' );
        return \eZFunctionHandler::execute( 'content', 'tree', array(
            'class_filter_type' => 'include',
            'class_filter_array' => \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'Classes' ),
            'parent_node_id' => $parents
        ) );
    }

    public function modify( $tpl, $operator_name, $operator_parameters, $root_namespace, $current_namespace, &$operator_value, $named_parameters )
    {
        if ( $operator_name !== self::OPERATOR_NAME )
        {
            $this->printError( 'Template operator "' . $operator_name . '" is not supported.' );
            return false;
        }

        $dictionary = $this->generateDictionary( $this->getWordNodes() );
        foreach( $dictionary as $word => $description )
        {
            $dict_tpl = \eZTemplate::factory();
            $dict_tpl->setVariable( 'dict_desc', $description );

            $case_sensitive = $named_parameters['case_sensitive'] === true ? '' : 'i';
            $pattern = '/(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $operator_value ) )
            {
                $operator_value = preg_replace( $pattern, $tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $operator_value );
            }
        }
    }

    /**
     * Returns a list of operator's parameters
     * @return type
     */
    public function namedParameterList()
    {
        return array(
            self::OPERATOR_NAME => array(
                'case_sensitive' => array(
                    'type' => 'boolean',
                    'required' => false,
                    'default' => \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'CaseSensitive' ) === 'true'
                 )
             )
        );
    }

    /**
     * This operator is named
     * @return boolean
     */
    public function namedParameterPerOperator()
    {
        return true;
    }

    /**
     * Returns a list of available oeprators
     * @return type
     */
    public function operatorList()
    {
        return $this->operators;
    }

    /**
     * Function prints the error text in the eZDebug.
     * @param string $text
     */
    private function printError( $text )
    {
        \eZDebug::writeError( self::EXTENSION_NAME . ': ' . $text );
    }
}