# Number lookup and phone validation

## Number lookup

Look up carrier information for any phone number. Results are cached:

```php
$result = Sent::lookup('+61412345678');

$result->data->isValid;       // bool
$result->data->carrierName;   // 'Telstra'
$result->data->lineType;      // 'mobile', 'landline', 'voip'
$result->data->isVoip;        // bool
$result->data->isPorted;      // bool
$result->data->countryCode;   // 'AU'
```

From the command line:

```bash
php artisan sent:lookup +61412345678
```

## Phone number validation

Validate E.164 format and optionally verify the number against the Sent.dm lookup API. Fails open if the API is unreachable, so a network blip never blocks a valid form submission.

```php
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', Rule::sentMobileNumber()],
        ];
    }
}
```

Require a mobile line (reject landlines and VoIP):

```php
'phone' => ['required', Rule::sentMobileNumber(requireMobile: true)],
```

Lookup validation tolerates connection failures, HTTP 429, and server errors.
Authentication, configuration, and programming errors are reported so they can be
fixed instead of silently accepting every number.
