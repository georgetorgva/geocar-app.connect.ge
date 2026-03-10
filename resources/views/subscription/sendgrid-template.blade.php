<!DOCTYPE html>

<html>

<head>
    <title>Subscription</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
</head>

<body>

<table style="margin:0; padding:0; border:0; border-collapse:collapse; border-spacing:0;" width="100%">
    <tbody style="margin:0; padding:0; border:0;">

    @foreach($templateData as $field => $data)
    <tr style="margin:0; padding:0; border:0;">
        <td style="margin:0; padding:0; border:0;" width="100%">
            @if(in_array($data["type"], $plainFieldTypes))

               @if($data["type"] === "title" || $data["type"] === "text")
                 <h2 style="margin-bottom: 20px">{{ $data["payload"] }}</h2>
               @else
                 <div style="margin-bottom: 20px; font-size: 14px;">
                     {!! $data["payload"] !!}
                 </div>
               @endif

            @else

                @if($data["type"] === "image")

                    @foreach($data["payload"] as $image)
                        <div style="margin-bottom: 20px;">
                            <img src="{{ $image["url"] }}" style="width: 100%; display: block;"/>
                        </div>
                    @endforeach

                @elseif($data["type"] === "file")

                    <div style="margin-bottom: 20px;">
                        <ul style="list-style-type: square; list-style-position: inside; padding-left: 0;">
                            @foreach($data["payload"] as $image)
                                <li style="margin-bottom: 6px; margin-left: 0; padding-left: 0;">
                                    <a style="text-decoration: underline; color: #5c5c5c;" target="_blank" title="{{ $image["title"] ?? "" }}" href="{{ $image["url"] }}"> {{ $image["title"] ?? $image["url"] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                @elseif($data["type"] === "url")

                    <div style="margin-bottom: 20px;">
                        <a style="text-decoration: underline; color: #5c5c5c;" target="_blank" title="{{ $data["payload"]["title"] ?? "" }}" href="{{ $data["payload"]["url"] }}"> {{ $data["payload"]["title"] ?? $data["payload"]["url"] }}</a>
                    </div>

                @endif

            @endif
        </td>
    </tr>
    @endforeach

    <tr>
        <hr/>

        <a style="decoration: underline;" href="{!! $manageSubscriptionUrl !!}?{!! $manageSubscriptionQueryStringPlaceholder !!}" target="_blank">{{ $manageSubscriptionTitle }}</a>
    </tr>

    </tbody>
</table>

</body>

</html>
