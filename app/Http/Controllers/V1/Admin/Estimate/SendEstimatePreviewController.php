<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendEstimatesRequest;
use Crater\Models\Estimate;
use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;

class SendEstimatePreviewController extends Controller
{
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate): HtmlString
    {
        $this->authorize('send estimate', $estimate);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $estimate->sendEstimateData($request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        return $markdown->render('emails.send.estimate', ['data' => $data]);
    }
}
