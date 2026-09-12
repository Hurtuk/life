import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'insertPics'
})
export class InsertPicsPipe implements PipeTransform {

  transform(value: string): string {
    console.log(value);
    return value
      .replace(/<p>(\[[^<]*\])<\/p>/gi, "<div class=\"photos\">$1</div>")
      .replace(
        /\[img ([a-zA-Z0-9]+\.[a-zA-Z]{3,4})\]/gi,
        "<img src=\"http://louiecinephile.fr/lifeBO/images/medias/$1\" />"
      );
  }

}
