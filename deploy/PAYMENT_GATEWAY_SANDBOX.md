# Payment Gateway Sandbox — ReVibe Market

## Midtrans
`.env`:
```env
PAYMENT_GATEWAY=midtrans
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
MIDTRANS_IS_PRODUCTION=false
```

Webhook:
```text
https://domainmu.com/api/payment_webhook_midtrans.php
```

Mapping:
- `settlement` / `capture` => paid
- `pending` => waiting_payment
- `expire` => expired
- `cancel` => cancelled
- `deny` / `failure` => failed
- `refund` => refunded

## Xendit
`.env`:
```env
PAYMENT_GATEWAY=xendit
XENDIT_API_KEY=xnd_development_xxxx
XENDIT_WEBHOOK_TOKEN=token-webhook
```

Webhook:
```text
https://domainmu.com/api/payment_webhook_xendit.php
```

Mapping:
- `PAID` / `SETTLED` => paid
- `PENDING` => waiting_payment
- `EXPIRED` => expired
- `FAILED` => failed

## Catatan keamanan
- Jangan commit key asli.
- `.env` tidak boleh masuk ZIP release.
- Webhook menolak signature/token tidak valid.
- Idempotency baru dicatat setelah update payment/order/escrow berhasil.
