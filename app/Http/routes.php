<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// GET Requests will be handled here
Route::get('/', 'HomeController@home');                                 	    // Homepage
Route::get('about', 'HomeController@about');                           	        // About us
Route::get('contact', 'HomeController@contact');                         	    // Contact
Route::get('downloads', 'HomeController@downloads');                     	    // Downloads
Route::get('licenses', 'HomeController@licenses');                      	    // Licenses
Route::get('assortment', 'HomeController@assortment');                          // Assortment
Route::get('download/{filename}', 'HomeController@download');                   // Download a file

Route::group(['middleware' => 'guest'], function () {
    Route::get('password/email', 'Auth\PasswordController@getEmail');		    // Forgot password page
    Route::get('password/reset/{token}', 'Auth\PasswordController@getReset');	// Password reset page
    Route::get('register', 'WebshopController@register');				        // Show the registration page
    Route::get('register/sent', 'WebshopController@registerSent');			    // Show the registration success page
    Route::get('forgotpassword', 'WebshopController@forgotPassword');       	// Forgot password page

    Route::post('login', 'WebshopController@login');                           	// Login handler
    Route::post('register', 'WebshopController@register_check');			    // Validate the registration reguest
    Route::post('forgotpassword', 'WebshopController@resetPassword');          	// Password reset handler
    Route::post('password/email', 'Auth\PasswordController@postEmail');		    // Reset password handler
    Route::post('password/reset', 'Auth\PasswordController@postReset');
});

Route::get('webshop', 'WebshopController@main');                        	    // Main webshop page
Route::get('product/{product_id?}', 'WebshopController@showProduct');   	    // Product page
Route::get('search', 'WebshopController@search');                       	    // Page with the search results
Route::get('specials', 'WebshopController@specials');                   	    // Show only the specials
Route::get('clearance', 'WebshopController@clearance');                 	    // Show only the clearance products

Route::group(['middleware' => 'auth.admin'], function() {

    Route::get('phpinfo', 'AdminController@phpinfo');                           // Display the phpinfo stuff

    Route::group(['prefix' => 'json'], function() {
        Route::get('load/cpu', 'AdminController@CPULoad');                      // Get CPU Load
        Route::get('load/ram', 'AdminController@RAMLoad');                      // Get RAM Load
        Route::get('chart/{data}', 'AdminController@chart');                    // Get data for a chart
    });

    Route::group(['prefix' => 'admin'], function() {
        Route::get('/', 'AdminController@overview');                        	// Admin overview
        Route::get('import', 'AdminController@import');                         // The page where the user can upload a CSV file with the products
        Route::get('importsuccess', 'AdminController@importSuccess');     	    // Import success page
        Route::get('managecontent', 'AdminController@contentManager');    	    // Content manager
        Route::get('generate', 'AdminController@generate');			            // Generate page
        Route::get('carousel', 'AdminController@carousel');			            // Carousel manager
        Route::get('getContent', 'AdminController@getContent');           	    // Get the content for a field
        Route::get('removeCarouselSlide/{id}', 'AdminController@removeSlide');	// Try to remove a carousel slide
        Route::get('usermanager', 'AdminController@userManager');	        	// Simple user manager
        Route::get('getUserData', 'AdminController@getUserData');	        	// Get the requested user's info
        Route::get('userAdded', 'AdminController@userAdded');			        // The user added page

        Route::post('productimport', 'ImportController@product');       	    // Handle the product import
        Route::post('discountimport', 'ImportController@discount');     	    // Handle the discount import
        Route::post('imageimport', 'ImportController@image');     		        // Handle the image import
        Route::post('downloadimport', 'ImportController@download');     	    // Handle the download file import
        Route::post('saveContent', 'AdminController@saveContent');           	// Save the page content
        Route::post('catalog', 'AdminController@generateCatalog');		        // Generate the catalog
        Route::post('addCarouselSlide', 'AdminController@addSlide');		    // Try to add a carousel slide
        Route::post('editCarouselSlide/{id}', 'AdminController@editSlide');	    // Edit the slide order
        Route::post('updateUser', 'AdminController@updateUser');			    // Update/add the user
        Route::post('pricelist', [
            'middleware' => 'RemoveTempFile',                                   // Middleware to remove the temp csv file after download
            'uses' => 'AdminController@generate_pricelist'                      // Generate a downloadable pricelist for a specified user
        ]);
    });
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('logout', 'WebshopController@logout');                      	    // Logout the current user
    Route::get('reorder/{order_id}', 'WebshopController@reorder');			    // Add the items from a previous order to the cart again

    Route::group(['prefix' => 'cart'], function () {
        Route::get('/', 'WebshopController@viewCart');               		    // Show the cart
        Route::get('destroy', 'WebshopController@cartDestroy');            	    // Remove all items from the cart
        Route::get('order/finished', 'WebshopController@orderFinished');		// Show the order finished page

        Route::post('add', 'WebshopController@addToCart');                      // Add product to cart
        Route::post('update', 'WebshopController@updateCart');                	// Update or remove product from cart
        Route::post('order', 'WebshopController@order');				        // Send the order
    });

    Route::group(['prefix' => 'account'], function () {
        Route::get('/', 'AccountController@overview');                          // Account overview
        Route::get('changepassword', 'AccountController@changePassGET');        // Change password page
        Route::get('favorites', 'AccountController@favorites');                 // Favorites
        Route::get('orderhistory', 'AccountController@orderhistory');           // Order history
        Route::get('addresslist', 'AccountController@addresslist');             // Addresslist
        Route::get('discountfile', 'AccountController@discountfile');           // ICC/CSV Discount generation page
        Route::get('generate_{type}/{method}', [
			'middleware' => 'RemoveTempFile', 			                        // Middleware to remove the temp csv/icc file after download
			'uses' => 'AccountController@generateFile'		                    // Discount file generation handler
		]);

        Route::post('changepassword', 'AccountController@changePassPOST'); 	    // Handle the change password request
        Route::post('addAddress', 'AccountController@addAddress');         	    // Add address to the database
        Route::post('removeAddress', 'AccountController@removeAddress');   	    // Remove address from the database
        Route::post('modFav', 'AccountController@modFav');                 	    // Change the favorites
        Route::post('isFav', 'AccountController@isFav');                   	    // Check the product array
    });
});

// POST Requests will be handled here
Route::when('*', 'csrf', array('post', 'put', 'delete'));

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);
