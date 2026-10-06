<?php
/**
 * Accessible FAQ accordion (Bootstrap collapse) plus FAQPage JSON-LD built
 * from the exact same entries, so the schema can never contain a question
 * users can't see.
 *
 * @var array<int, array{q: string, a: string}> $faqs answers may contain trusted inline HTML
 * @var string $heading
 */
$accordionId = 'faqAccordion';
?>
<section class="seo-section" aria-labelledby="faq-heading">
  <h2 id="faq-heading" class="seo-h2"><?= e($heading) ?></h2>
  <div class="accordion faq-accordion" id="<?= $accordionId ?>">
    <?php foreach ($faqs as $index => $faq): ?>
      <div class="accordion-item">
        <h3 class="accordion-header" id="faq-h-<?= $index ?>">
          <button class="accordion-button<?= $index === 0 ? '' : ' collapsed' ?>" type="button"
                  data-bs-toggle="collapse" data-bs-target="#faq-c-<?= $index ?>"
                  aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="faq-c-<?= $index ?>">
            <?= e($faq['q']) ?>
          </button>
        </h3>
        <div id="faq-c-<?= $index ?>" class="accordion-collapse collapse<?= $index === 0 ? ' show' : '' ?>"
             aria-labelledby="faq-h-<?= $index ?>" data-bs-parent="#<?= $accordionId ?>">
          <div class="accordion-body text-secondary"><?= $faq['a'] ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?= \App\Services\SeoSchema::script(\App\Services\SeoSchema::faq($faqs)) ?>
