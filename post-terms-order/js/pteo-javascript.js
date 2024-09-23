    
    var PTeO = {
            
           
                
            change_view_selection   :   function(element)
                {
                    window.location = jQuery(element).val();   
                },
                
            
                
        }
        
        
        
    jQuery(document).ready(function()
        {
            jQuery( '.tips' ).tipTip({
                                    'attribute': 'data-tip',
                                    'fadeIn': 50,
                                    'fadeOut': 50,
                                    'delay': 200,
                                    'edgeOffset': 6,
                                });

    });