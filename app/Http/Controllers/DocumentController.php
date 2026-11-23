<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Setting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private function permit(Booking $booking): void
    {
        Gate::authorize('view', $booking);
        abort_unless($booking->payment_status === 'lunas', 404);
    }

    private function logo(): ?string
    {
        $path = Setting::find('logo')?->value;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($path).';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    private function qr(string $value): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(110), new SvgImageBackEnd)))->writeString($value);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function ticket(Booking $booking)
    {
        $this->permit($booking);
        $booking->load(['schedule.route', 'schedule.vehicle', 'passengers.seat']);
        $qr = $this->qr(route('verify.ticket', $booking->code));
        $logoData = $this->logo();

        return view('documents.ticket', compact('booking', 'qr', 'logoData'));
    }

    public function ticketPdf(Booking $booking)
    {
        $this->permit($booking);
        $booking->load(['schedule.route', 'schedule.vehicle', 'passengers.seat']);
        $qr = $this->qr(route('verify.ticket', $booking->code));
        $logoData = $this->logo();

        return Pdf::loadView('documents.ticket', compact('booking', 'qr', 'logoData'))->setPaper('a4')->download('tiket-'.$booking->code.'.pdf');
    }

    public function invoice(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        $invoice->load('items', 'booking');
        $qr = $this->qr(route('verify.invoice', $invoice->number));
        $logoData = $this->logo();

        return view('documents.invoice', compact('invoice', 'qr', 'logoData'));
    }

    public function invoicePdf(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        $invoice->load('items', 'booking');
        $qr = $this->qr(route('verify.invoice', $invoice->number));
        $logoData = $this->logo();

        return Pdf::loadView('documents.invoice', compact('invoice', 'qr', 'logoData'))->setPaper('a4')->download('faktur-'.$invoice->number.'.pdf');
    }

    public function verifyTicket(string $code)
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        return view('documents.verify', ['label' => 'Tiket', 'number' => $code, 'valid' => $booking->payment_status === 'lunas']);
    }

    public function verifyInvoice(string $number)
    {
        $invoice = Invoice::where('number', $number)->firstOrFail();

        return view('documents.verify', ['label' => 'Faktur', 'number' => $number, 'valid' => $invoice->status === 'lunas']);
    }
}
