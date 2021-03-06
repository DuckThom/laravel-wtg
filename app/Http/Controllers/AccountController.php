<?php namespace App\Http\Controllers;

use App\Address;
use App\Order;
use App\User;

use DB, Auth, Redirect, Input, Request, Validator, Log, Hash, File, Response, Session, Mail, Helper;

class AccountController extends Controller {

    /*
    |--------------------------------------------------------------------------
    | Account Controller
    |--------------------------------------------------------------------------
    |
    | This controller will process the requests for the pages:
    |       - Overview
    |       - Change Password
    |       - Favorites
    |       - Address list
    |       - Order history
    |       - ICC/CSV file generation page
    |
    */

    /**
     * The overview for the account page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function overview()
    {
        $orderCount = Order::where('User_id', Auth::user()->login)->count();

        return view('account.overview', [
            'orderCount' => $orderCount
        ]);
    }

    /**
     * The change password page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function changePassGET()
    {
        return view('account.changePass');
    }

    /**
     * Change password handler
     *
     * @return mixed
     */
    public function changePassPOST()
    {
        if (Input::has('oldPass') && Input::has('newPass') && Input::has('newPassVerify'))
        {
            $oldPass        = Input::get('oldPass');
            $newPass        = Input::get('newPass');
            $newPassVerify  = Input::get('newPassVerify');

            if (Auth::validate(['login' => Auth::user()->login, 'password' => $oldPass]))
            {
                if ($newPass === $newPassVerify)
                {
                    $hashedPass     = Hash::make($newPass);
                    $user           = User::find(Auth::id());

                    $user->password = $hashedPass;

                    $user->save();

                    return redirect('account')->with('status', 'Uw wachtwoord is gewijzigd');
                } else
                {
                    return redirect('account/changepassword')->withErrors('De nieuwe wachtwoorden komen niet overeen');
                }
            } else
            {
                Log::warning('User: ' . Auth::user()->login . ' tried to change password but entered the wrong password.');
                return redirect('account/changepassword')->withErrors('Het oude wachtwoord is onjuist!');
            }
        } else
            return redirect('account/changepassword')->withErrors('Niet alle velden zijn ingevuld');
    }

    /**
     * This will fetch the favorites list from the database and
     * transform it into a list categorised by series
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function favorites()
    {
        $favoritesArray = unserialize(Auth::user()->favorites);
        $seriesData     = [];
        $productGroup   = [];

        // Get the product data
        $productData    = DB::table('products')->whereIn('number', $favoritesArray)->get();

        // Store each serie from the products in a seperate array for categorisation
        foreach ($productData as $product)
            array_push($seriesData, $product->series);

        // Only keep the unique values
        $seriesData = array_unique($seriesData);

        // Put the product and serie data in a new array
        foreach ($seriesData as $key => $serie) {
            foreach ($productData as $product) {
                if ($product->series == $serie) {
                    $productGroup[$serie][] = $product;
                }
            }
        }

        return view('account.favorites', [
                'favorites'     => $productData,
                'discounts'     => Helper::getProductDiscount(Auth::user()->login),
                'groupData'     => $productGroup
            ]
        );
    }

    /**
     * Update the favourites from a user
     *
     * @return $this|string
     */
    public function modFav()
    {
        if (Request::ajax())
        {
            $product = Input::get('product');

            $validator = Validator::make(
                ['product' => $product],
                ['product' => 'required|digits:7']
            );

            if (!$validator->fails())
            {
                $currentFavorites = unserialize(Auth::user()->favorites);

                // Remove the product from the favorites if it is already in
                if (in_array($product, $currentFavorites))
                {
                    $key = array_search($product, $currentFavorites);

                    // Remove the product from the favorites array
                    unset($currentFavorites[$key]);

                    // Save the new favorites array to the database
                    $user = User::find(Auth::user()->id);
                    $user->favorites = serialize($currentFavorites);
                    $user->save();

                    echo 'SUCCESS';
                    exit();
                } else
                {
                    // Add the product to the favorites array
                    array_push($currentFavorites, $product);

                    // Save the new favorites array to the database
                    $user = User::find(Auth::user()->id);
                    $user->favorites = serialize($currentFavorites);
                    $user->save();

                    echo 'SUCCESS';
                    exit();
                }
            } else
                return 'FAILED';
        } else
            return redirect()->back()->withErrors( 'Geen toegang!');
    }

    /**
     * Check if the product is in the favorites array
     *
     * @return $this|string
     */
    public function isFav()
    {
        if (Request::ajax())
        {
            $product = Input::get('product');

            $validator = Validator::make(
                ['product' => $product],
                ['product' => 'required|digits:7']
            );

            if (!$validator->fails())
            {
                $currentFavorites = unserialize(Auth::user()->favorites);

                if (in_array($product, $currentFavorites))
                    return 'IN_ARRAY';
                else
                    return 'NOT_IN_ARRAY';
            } else
                return 'FAILED';
        } else
            return redirect()->back()->withErrors( 'Geen toegang!');
    }

    /**
     * This page will show the orderhistory
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function orderhistory()
    {
        $orderList = Order::where('User_id', Auth::user()->login)->orderBy('created_at', 'desc')->paginate(15);

        return view('account.orderhistory', ['orderlist' => $orderList]);
    }

    /**
     * The address list page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function addresslist()
    {
        $addressList = Address::where('User_id', Auth::user()->login)->get();

        return view('account.addresslist', ['addresslist' => $addressList]);
    }

    /**
     * Handle the add address request
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function addAddress()
    {
        if (Input::has('name') && Input::has('street') && Input::has('postcode') && Input::has('city'))
        {
            $name           = Input::get('name');
            $street         = Input::get('street');
            $postcode       = Input::get('postcode');
            $city           = Input::get('city');
            $telephone      = (Input::has('telephone') ? Input::get('telephone') : '');
            $mobile         = (Input::has('mobile') ? Input::get('mobile') : '');

            $validator = Validator::make([
                'name'          => $name,
                'street'        => $street,
                'postcode'      => $postcode,
                'city'          => $city,
                'telephone'     => $telephone,
                'mobile'        => $mobile
            ],
                [
                    'name'          => 'required',
                    'street'        => 'required|regex:/^[a-zA-Z0-9\s]+$/',
                    'postcode'      => 'required|regex:/^[a-zA-Z0-9\s]+$/|between:6,8',
                    'city'          => 'required|regex:/^[a-zA-Z\s]+$/',
                    'telephone'     => 'regex:/^[0-9\s\-]+$/',
                    'mobile'        => 'regex:/^[0-9\s\-]+$/'
                ]
            );

            if (!$validator->fails())
            {
                $address = new Address;

                $address->name          = $name;
                $address->street        = $street;
                $address->postcode      = $postcode;
                $address->city          = $city;
                $address->telephone     = $telephone;
                $address->mobile        = $mobile;
                $address->User_id       = Auth::user()->login;

                $address->save();

                return redirect()->back()->with('status', 'Het adres is toegevoegd');
            } else
            {
                $messages = $validator->errors();
                $msg = '';

                foreach($messages->all() as $key => $message)
                    $msg .= ucfirst($message) . "\r\n";

                return redirect()->back()->withErrors( $msg);
            }

        } else
            return redirect()->back()->withErrors( 'Een of meer vereiste velden zijn leeg');
    }

    /**
     * This function handles the removal of an address
     *
     * @return mixed
     */
    public function removeAddress()
    {
        if (Input::has('id'))
        {
            $address = Address::where('id', Input::get('id'))->where('User_id', Auth::user()->login)->firstOrFail();

            if (!empty($address))
            {
                $address->delete();

                return redirect('account/addresslist')->with('status', 'Het adres is verwijderd');
            } else
                return redirect('account/addresslist')->withErrors('Het adres bestaat niet of behoort niet bij uw account');
        } else
            return redirect('account/addresslist')->withErrors('Geen adres id aangegeven');
    }

    /**
     * The user is able to download their discounts file from here in ICC and CSV format
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function discountfile()
    {
        return view('account.discountfile');
    }

    /**
     * This will handle the requests for the generation of the discounts file
     *
     * @param $type
     * @param $method
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function generateFile($type, $method)
    {
        if ($type === 'icc')
        {
            if ($method === 'download')
            {
                // Create a filesystem link to the temp file
                $filename       = storage_path() . '/icc_data' . Auth::user()->login . '.txt';

                // Store the path in flash data so the middleware can delete the file afterwards
                Session::flash('file.download', $filename);

                // File the file with discount data
                File::put($filename, AccountController::discountICC());

                // Return the data as a downloadable file: 'icc_data.txt'
                return Response::download($filename, 'icc_data' . Auth::user()->login . '.txt');

            } elseif ($method === 'mail')
            {
                $filename = storage_path() . '/icc_data' . Auth::user()->login . '.txt';

                // Store the path in flash data so the middleware can delete the file afterwards
                Session::flash('file.download', $filename);

                File::put($filename, AccountController::discountICC());

                Mail::send('email.discountfile', [], function($message) use ($filename)
                {
                    $message->from('verkoop@wiringa.nl', 'Wiringa Webshop');

                    $message->to(Auth::user()->email);

                    $message->subject('WTG Webshop ICC kortingen');

                    $message->attach($filename, ['as' => 'icc_data' . Auth::user()->login . '.txt']);
                });

                return redirect('account/discountfile')->with('status', 'Het kortingsbestand is verzonden naar ' . Auth::user()->email);
            } else
                return redirect('account/discountfile')->withErrors( 'Geen verzendmethode opgegeven');
        } elseif ($type === 'csv')
        {
            if ($method === 'download')
            {
                // Create a filesystem link to the temp file
                $filename = storage_path() . '/icc_data' . Auth::user()->login . '.csv';

                // Store the path in flash data so the middleware can delete the file afterwards
                Session::flash('file.download', $filename);

                File::put($filename, AccountController::discountCSV());

                return Response::download($filename, 'icc_data' . Auth::user()->login . '.csv');

            } elseif ($method === 'mail')
            {
                $filename = storage_path() . '/icc_data' . Auth::user()->login . '.csv';

                // Store the path in flash data so the middleware can delete the file afterwards
                Session::flash('file.download', $filename);

                File::put($filename, AccountController::discountCSV());

                Mail::send('email.discountfile', [], function($message) use ($filename)
                {
                    $message->from('verkoop@wiringa.nl', 'Wiringa Webshop');

                    $message->to(Auth::user()->email);

                    $message->subject('WTG Webshop CSV kortingen');

                    $message->attach($filename, ['as' => 'icc_data' . Auth::user()->login . '.csv']);
                });

                return redirect('account/discountfile')->with('status', 'Het kortingsbestand is verzonden naar ' . Auth::user()->email);
            } else
                return redirect('account/discountfile')->withErrors('Geen verzendmethode opgegeven');
        } else
            return redirect('account/discountfile')->withErrors( 'Ongeldig bestands type');
    }

    /**
     * This function will generate the data for the ICC file
     *
     * @return string
     */
    private function discountICC()
    {
        /**
         * These variables are static.
         * We only need to set them once
         */
        $GLN = 8714253038995;
        $empty1 = '       ';
        $debiteur = Auth::user()->login;
        $empty2 = '               ';
        $date = date('Ymd');
        $version = '1.1  ';
        $name = Auth::user()->company;

        /*
         * Used in the rows containing the discounts
         */
        $korting2 = '00000';
        $korting3 = '00000';
        $nettoprijs = '000000000';
        $startdatum = $date;
        $einddatum = 99991231;

        while (strlen($name) <= 70) {
            $name .= ' ';
        }

        $text = '';

        /*
         * Append the "Groepsgebonden" discounts to the ICC file
         */
        $query = DB::table('discounts')
            ->where('User_id', $debiteur)
            ->where('table', 'VA-220')
            ->where('group_desc', '!=', 'Vervallen');

        $groep_korting = $query->get();
        $count = $query->count();

        foreach ($groep_korting as $korting) {
            $groepsnummer = $korting->product;
            while (strlen($groepsnummer) < 20) {
                $groepsnummer .= ' ';
            }
            $artikelnummer = '                    '; //20 empty positions
            $omschrijving = preg_replace("/(\r)|(\n)/", "", $korting->group_desc);
            while (strlen($omschrijving) < 50) {
                $omschrijving .= ' ';
            }
            $korting1 = '0' . preg_replace("/\,/", "", $korting->discount);
            while (strlen($korting1) < 5) {
                $korting1 .= '0';
            }

            $text .= $groepsnummer . $artikelnummer . $omschrijving . $korting1 . $korting2 . $korting3 . $nettoprijs . $startdatum . $einddatum . "\r\n";
        }

        /*
         * Append the "Standaard" discounts to the ICC file
         */
        $query = DB::table('discounts')
            ->where('table', 'VA-221')
            ->where('group_desc', '!=', 'Vervallen')
            ->whereNotIn('product', function($query) use ($debiteur) {
                $query->select('product')
                    ->from('discounts')
                    ->where('table', 'VA-220')
                    ->where('User_Id', $debiteur);
            });

        $default_korting = $query->get();
        $count = $query->count() + $count;

        foreach ($default_korting as $korting) {
            $groepsnummer = $korting->product;
            while (strlen($groepsnummer) < 20) {
                $groepsnummer .= ' ';
            }
            $artikelnummer = '                    '; //20 empty positions
            $omschrijving = preg_replace("/(\r)|(\n)/", "", $korting->group_desc);
            while (strlen($omschrijving) < 50) {
                $omschrijving .= ' ';
            }
            $korting1 = '0' . preg_replace("/\,/", "", $korting->discount);
            while (strlen($korting1) < 5) {
                $korting1 .= '0';
            }

            $text .= $groepsnummer . $artikelnummer . $omschrijving . $korting1 . $korting2 . $korting3 . $nettoprijs . $startdatum . $einddatum . "\r\n";
        }

        /*
         * Append the "Global Product" discounts to the ICC file
         */
        $query = DB::table('discounts')
            ->where('table', 'VA-261')
            ->whereNotIn('product', function($query) use ($debiteur) {
                $query->select('product')
                    ->from('discounts')
                    ->where('table', 'VA-260')
                    ->where('User_Id', $debiteur);
            });

        $product_korting = $query->get();
        $count = $query->count() + $count;

        foreach ($product_korting as $korting) {
            $groepsnummer = '                    '; //20 empty positions
            $artikelnummer = $korting->product;
            while (strlen($artikelnummer) < 20) {
                $artikelnummer .= ' ';
            }
            $omschrijving = preg_replace("/(\r)|(\n)/", "", $korting->product_desc);
            while (strlen($omschrijving) < 50) {
                $omschrijving .= ' ';
            }
            $korting1 = '0' . preg_replace("/\,/", "", $korting->discount);
            while (strlen($korting1) < 5) {
                $korting1 .= '0';
            }

            $text .= $groepsnummer . $artikelnummer . $omschrijving . $korting1 . $korting2 . $korting3 . $nettoprijs . $startdatum . $einddatum . "\r\n";
        }

        /*
         * Append the "Productgebonden" discounts to the ICC file
         */
        $query = DB::table('discounts')
            ->where('User_id', $debiteur)
            ->where('table', 'VA-260');

        $product_korting = $query->get();
        $count = $query->count() + $count;

        foreach ($product_korting as $korting) {
            $groepsnummer = '                    '; //20 empty positions
            $artikelnummer = $korting->product;
            while (strlen($artikelnummer) < 20) {
                $artikelnummer .= ' ';
            }
            $omschrijving = preg_replace("/(\r)|(\n)/", "", $korting->product_desc);
            while (strlen($omschrijving) < 50) {
                $omschrijving .= ' ';
            }
            $korting1 = '0' . preg_replace("/\,/", "", $korting->discount);
            while (strlen($korting1) < 5) {
                $korting1 .= '0';
            }

            $text .= $groepsnummer . $artikelnummer . $omschrijving . $korting1 . $korting2 . $korting3 . $nettoprijs . $startdatum . $einddatum . "\r\n";
        }

        /*
         * Prepend the first row last so the count doesnt mess up.
         */
        $count = sprintf("%'06d", $count);
        $text = $GLN . $empty1 . $debiteur . $empty2 . $date . $count . $version . $name . "\r\n" . $text;

        return $text;
    }

    /**
     * This function will generate the data for the CSV file
     *
     * @return string
     */
    private function discountCSV()
    {
        // Static variables
        $firstRow 	= 'Artikelnr;Omschrijving;Kortingspercentage;ingangsdatum' . "\r\n";
        $debiteur 	= Auth::user()->login;
        $date		= date('Y-m-d');
        $delimiter	= ';';

        $text 		= $firstRow;

        /*
         * Append the "Groepsgebonden" discounts to the CSV file
         */
        $query = DB::table('discounts')
            ->where('User_id', $debiteur)
            ->where('table', 'VA-220')
            ->where('group_desc', '!=', 'Vervallen');

        $groep_korting = $query->get();

        foreach ($groep_korting as $korting) {
            $groepsnummer 	= $korting->product;
            $omschrijving 	= preg_replace("/(\r)|(\n)/", "", $korting->group_desc);
            $korting1 	    = $korting->discount . "%";

            $text 	       .= $groepsnummer . $delimiter . $omschrijving . $delimiter . $korting1 . $delimiter . $date . "\r\n";
        }

        /*
         * Append the "Standaard" discounts to the CSV file
         */
        $query = DB::table('discounts')
            ->where('table', 'VA-221')
            ->where('group_desc', '!=', 'Vervallen')
            ->whereNotIn('product', function($query) use ($debiteur) {
                $query->select('product')
                    ->from('discounts')
                    ->where('table', 'VA-220')
                    ->where('User_Id', $debiteur);
            });

        $default_korting = $query->get();

        foreach ($default_korting as $korting) {
            $groepsnummer 	= $korting->product;
            $omschrijving 	= preg_replace("/(\r)|(\n)/", "", $korting->group_desc);
            $korting1 	= $korting->discount . "%";

            $text 	       .= $groepsnummer . $delimiter . $omschrijving . $delimiter . $korting1 . $delimiter . $date . "\r\n";
        }

        /*
         * Append the "Global Product" discounts to the ICC file
         */
        $query = DB::table('discounts')
            ->where('table', 'VA-261')
            ->whereNotIn('product', function($query) use ($debiteur) {
                $query->select('product')
                    ->from('discounts')
                    ->where('table', 'VA-260')
                    ->where('User_Id', $debiteur);
            });

        $product_korting = $query->get();

        foreach ($product_korting as $korting) {
            $artikelnummer = $korting->product;
            $omschrijving  = preg_replace("/(\r)|(\n)/", "", $korting->product_desc);
            $korting1      = $korting->discount . "%";

            $text .= $artikelnummer . $delimiter . $omschrijving . $delimiter . $korting1 . $delimiter . $date . "\r\n";
        }

        /*
         * Append the "Productgebonden" discounts to the CSV file
         */
        $query = DB::table('discounts')
            ->where('User_id', $debiteur)
            ->where('table', 'VA-260');

        $product_korting = $query->get();

        foreach ($product_korting as $korting) {
            $artikelnummer 	= $korting->product;
            $omschrijving 	= preg_replace("/(\r)|(\n)/", "", $korting->product_desc);
            $korting1 	    = $korting->discount . "%";

            $text 	       .= $artikelnummer . $delimiter . $omschrijving . $delimiter . $korting1 . $delimiter . $date . "\r\n";
        }

        return $text;
    }
}
