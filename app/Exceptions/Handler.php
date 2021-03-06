<?php namespace App\Exceptions;

use Exception, Redirect;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler {

	/**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
		NotFoundHttpException::class,
        HttpException::class,
        ModelNotFoundException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        // Create a sentry variable
        $sentry = app('sentry');

        // Add the user login if someone is logged in
        if (auth()->check()) {
            $sentry->user_context([
                'id'        => auth()->user()->login,
                'username'  => auth()->user()->company
            ]);
        }

        // Send reportable errors with level 'Error'
        if ($this->shouldReport($e)) {
            $sentry->captureException($e);
        // Else send them with warning and only if someone is logged in
        } else if (auth()->check()) {
            $sentry->captureException($e, [
                'level' => 'warning'
            ]);
        }

		$trace = $e->getTraceAsString();
		$class = get_class($e);

		if ( env('APP_ENV') === "production" && 
             !$e instanceof ModelNotFoundException &&
		     !$e instanceof MethodNotAllowedHttpException &&
			 !$e instanceof TokenMismatchException &&
			 !$e instanceof NotFoundHttpException)
		{
			\Mail::send('email.exception', ['trace' => $trace, 'class' => $class], function($message)
			{
					$message->from('verkoop@wiringa.nl', 'Wiringa Webshop');

					$message->to('thomas.wiringa@gmail.com');

					$message->subject('[WTG Webshop] Whoops, looks like something went wrong');
			});
		}

        return parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof ModelNotFoundException || $e instanceof MethodNotAllowedHttpException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        }

		if ($e instanceof TokenMismatchException) {
			return Redirect::to('/')->withErrors("Uw sessie is verlopen, log opnieuw in en probeer het opnieuw");
		}

		if ($this->isHttpException($e)) {
			return $this->renderHttpException($e);
		}

		return parent::render($request, $e);
	}

}
